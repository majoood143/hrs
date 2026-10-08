<?php

namespace Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Fields\Types\FileField;
use Packstub\FormBuilder\Fields\Types\SelectField;
use Packstub\FormBuilder\Filament\EditorLanguages;
use Packstub\FormBuilder\Filament\FieldBlocks;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\CreateForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Support\FormPreview;
use Packstub\FormBuilder\Support\FormTemplates;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/**
 * The form editor's layout: name first, slug and keys from English whatever the app's
 * locale, a grouped and described "Add field" menu, fine-tuning under "More options",
 * choice values filled from their label, file sizes in MB.
 */
class FormBuilderEditorTest extends TestCase
{
    use MakesOrderForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_slug_comes_from_the_english_name_even_on_an_arabic_default_locale(): void
    {
        config(['languages.default' => 'ar']);

        Livewire::test(CreateForm::class)
            ->assertSee(__('packstub-form-builder::form-builder.fields.name').' ('.config('languages.available.en.native').')')
            ->set('data.name.ar', 'تواصل معنا')
            ->assertSet('data.slug', null)
            ->set('data.name.en', 'Contact us')
            ->assertSet('data.slug', 'contact-us')
            ->call('create')
            ->assertHasNoFormErrors();

        $form = Form::query()->where('slug', 'contact-us')->firstOrFail();
        $this->assertSame('تواصل معنا', $form->getTranslation('name', 'ar'));
    }

    public function test_the_english_name_is_the_required_one(): void
    {
        config(['languages.default' => 'ar']);

        Livewire::test(CreateForm::class)
            ->set('data.name.ar', 'تواصل معنا')
            ->set('data.slug', 'contact')
            ->call('create')
            ->assertHasFormErrors(['name.en' => 'required']);
    }

    public function test_a_field_key_comes_from_the_english_label(): void
    {
        config(['languages.default' => 'ar']);
        app()->setLocale('ar');
        $form = $this->makeForm();

        $page = $this->editor($form);
        $item = $this->itemKey($page);

        $page->set("data.fields.{$item}.data.key", '')
            ->set("data.fields.{$item}.data.label.ar", 'الاسم الكامل')
            ->assertSet("data.fields.{$item}.data.key", '')
            ->set("data.fields.{$item}.data.label.en", 'Your name')
            ->assertSet("data.fields.{$item}.data.key", 'your_name');

        // a key left blank is filled on save, from the English label too
        $form->update(['fields' => [['type' => 'text', 'data' => ['key' => '', 'label' => ['en' => 'Full name', 'ar' => 'الاسم الكامل']]]]]);
        $this->assertSame('full_name', $form->fresh()->fields[0]['data']['key']);
    }

    public function test_the_add_field_menu_is_grouped_and_described(): void
    {
        $order = FieldBlocks::orderedTypes()->keys()->all();

        $this->assertSame(
            ['text', 'number', 'textarea', 'date', 'email', 'phone', 'url', 'nationality',
                'select', 'radio', 'conditional_radio', 'checkbox', 'checkboxes', 'hidden', 'file', 'heading', 'paragraph'],
            $order,
        );

        $type = app(FieldTypeRegistry::class)->get('conditional_radio');
        $menuLabel = FieldBlocks::block($type)->getLabel();

        $this->assertInstanceOf(HtmlString::class, $menuLabel);
        $this->assertStringContainsString(e($type->label()), $menuLabel->toHtml());
        $this->assertStringContainsString(e($type->description()), $menuLabel->toHtml());

        // a field in the form: its label and type, no description
        $header = FieldBlocks::block($type)->getLabel(['label' => ['en' => 'Travelled', 'ar' => 'سافر']])->toHtml();
        $this->assertStringContainsString('Travelled', $header);
        $this->assertStringContainsString(e($type->label()), $header);
        $this->assertStringNotContainsString(e($type->description()), $header);
    }

    public function test_a_folded_field_shows_badges_for_required_half_width_and_a_missing_language(): void
    {
        $text = app(FieldTypeRegistry::class)->get('text');
        $badge = fn (string $key, array $replace = []): string => e(__("packstub-form-builder::form-builder.editor.{$key}", $replace));

        $plain = FieldBlocks::itemLabel($text, ['label' => ['en' => 'Name', 'ar' => 'الاسم']])->toHtml();
        $this->assertStringNotContainsString($badge('badge_required'), $plain);
        $this->assertStringNotContainsString($badge('width_half'), $plain);
        $this->assertStringNotContainsString($badge('badge_missing', ['language' => 'AR']), $plain);

        $flagged = FieldBlocks::itemLabel($text, ['label' => ['en' => 'Name', 'ar' => ''], 'required' => true, 'width' => 'half'])->toHtml();
        $this->assertStringContainsString($badge('badge_required'), $flagged);
        $this->assertStringContainsString($badge('width_half'), $flagged);
        $this->assertStringContainsString($badge('badge_missing', ['language' => 'AR']), $flagged);

        // labels are escaped
        $this->assertStringContainsString('&lt;b&gt;', FieldBlocks::itemLabel($text, ['label' => ['en' => '<b>', 'ar' => '<b>']])->toHtml());

        // a paragraph has no label to translate
        $paragraph = app(FieldTypeRegistry::class)->get('paragraph');
        $this->assertStringNotContainsString($badge('badge_missing', ['language' => 'AR']), FieldBlocks::itemLabel($paragraph, ['label' => ['en' => 'Intro', 'ar' => '']])->toHtml());
    }

    public function test_the_default_value_input_matches_the_field_type(): void
    {
        $registry = app(FieldTypeRegistry::class);

        $this->assertInstanceOf(Select::class, $registry->get('select')->defaultInput());
        $this->assertInstanceOf(Select::class, $registry->get('radio')->defaultInput());
        $this->assertInstanceOf(Select::class, $registry->get('nationality')->defaultInput());
        $this->assertInstanceOf(DatePicker::class, $registry->get('date')->defaultInput());
        $this->assertInstanceOf(TextInput::class, $registry->get('text')->defaultInput());
        $this->assertTrue($registry->get('number')->defaultInput()->isNumeric());
    }

    public function test_a_default_that_is_no_longer_a_choice_survives_a_save(): void
    {
        $form = $this->makeForm();
        $form->update(['fields' => [['type' => 'select', 'data' => [
            'key' => 'colour',
            'label' => ['en' => 'Colour', 'ar' => 'اللون'],
            'default' => 'green',
            'choices' => [['value' => 'red', 'label' => ['en' => 'Red', 'ar' => 'أحمر']]],
        ]]]]);

        $this->editor($form)->call('save')->assertHasNoFormErrors();

        $this->assertSame('green', $form->fresh()->fields[0]['data']['default']);
    }

    public function test_pasted_lines_become_choices(): void
    {
        $existing = ['a' => ['value' => 'yes', 'label' => ['en' => 'Yes', 'ar' => 'نعم']], 'b' => ['value' => '', 'label' => ['en' => '', 'ar' => '']]];

        $choices = array_values(SelectField::withPastedChoices($existing, "Yes | نعم\nNo | لا\n\nBy car\tبالسيارة\n  Maybe  \nربما\nNo"));

        $this->assertSame([
            ['value' => 'yes', 'label' => ['en' => 'Yes', 'ar' => 'نعم']],
            ['label' => ['en' => 'No', 'ar' => 'لا'], 'value' => 'no'],
            ['label' => ['en' => 'By car', 'ar' => 'بالسيارة'], 'value' => 'by_car'],
            ['label' => ['en' => 'Maybe', 'ar' => ''], 'value' => 'maybe'],
            ['label' => ['en' => '', 'ar' => 'ربما'], 'value' => 'rbma'],
        ], $choices);
    }

    public function test_paste_a_list_fills_the_choices_in_the_editor(): void
    {
        $form = $this->makeForm();
        $form->update(['fields' => [['type' => 'radio', 'data' => [
            'key' => 'answer',
            'label' => ['en' => 'Answer', 'ar' => 'الإجابة'],
            'choices' => [['value' => 'yes', 'label' => ['en' => 'Yes', 'ar' => 'نعم']]],
        ]]]]);

        $page = $this->editor($form);
        $item = $this->itemKey($page);

        $page->callAction(
            TestAction::make('pasteChoices')->schemaComponent("fields.{$item}.data.choices"),
            ['lines' => "No | لا\nYes | نعم"],
        )->assertHasNoFormErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['yes', 'no'], array_column($form->fresh()->fields[0]['data']['choices'], 'value'));
    }

    public function test_the_redirect_must_be_a_web_address_or_a_site_path(): void
    {
        $form = $this->makeForm();

        $this->editor($form)->fillForm(['redirect_url' => 'thank-you'])->call('save')->assertHasFormErrors(['redirect_url' => 'regex']);
        $this->editor($form)->fillForm(['redirect_url' => '/thank-you'])->call('save')->assertHasNoFormErrors();
        $this->editor($form)->fillForm(['redirect_url' => 'https://example.com/thanks'])->call('save')->assertHasNoFormErrors();
    }

    public function test_a_warning_shows_when_submissions_would_be_kept_nowhere(): void
    {
        $form = $this->makeForm();
        $warning = __('packstub-form-builder::form-builder.fields.nothing_kept');

        $this->editor($form)
            ->assertDontSee($warning)
            ->fillForm(['store_submissions' => false, 'notification_emails' => []])
            ->assertSee($warning)
            ->fillForm(['notification_emails' => ['office@example.com']])
            ->assertDontSee($warning);
    }

    public function test_the_preview_shows_unsaved_changes_to_its_own_admin_only(): void
    {
        $this->withoutVite();
        $form = $this->makeForm();

        $page = $this->editor($form);
        $item = $this->itemKey($page);
        $page->set("data.fields.{$item}.data.label.en", 'Unsaved name label')
            ->set("data.fields.{$item}.data.label.ar", 'اسم غير محفوظ')
            ->set('data.is_active', false)
            ->mountAction('preview');

        preg_match('#form-preview/([A-Za-z0-9]{40})\?locale=en#', $page->getMountedActionModalHtml(), $match);
        $this->assertNotEmpty($match, 'the slide-over loads the preview page');
        $token = $match[1];

        $this->get(FormPreview::url($token, 'en'))
            ->assertOk()
            ->assertSee('Unsaved name label')
            ->assertSee(__('packstub-form-builder::form-builder.frontend.submit'))
            ->assertDontSee(__('packstub-form-builder::form-builder.frontend.closed'));

        $this->get(FormPreview::url($token, 'ar'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('اسم غير محفوظ');

        // the admin's own language is untouched (?locale=, not ?lang=)
        $this->assertNotSame('ar', session('locale'));

        // nothing was saved
        $this->assertSame('Full name', $form->fresh()->fields[0]['data']['label']['en']);

        // someone else, or nobody, cannot open it
        $this->actingAs(new class(['id' => 99, 'name' => 'Other']) extends Authenticatable
        {
            protected $guarded = [];
        });
        $this->get(FormPreview::url($token, 'en'))->assertNotFound();
        $this->get(FormPreview::url('nope', 'en'))->assertNotFound();
    }

    public function test_every_type_has_a_description_in_both_languages(): void
    {
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            foreach (app(FieldTypeRegistry::class)->all() as $id => $type) {
                $this->assertNotNull($type->description(), "{$id} has no {$locale} description");
            }
        }
    }

    public function test_fine_tuning_is_under_more_options_and_an_existing_form_saves_unchanged(): void
    {
        $form = $this->makeForm(withFile: true);
        $form->update(['fields' => [
            ...$form->fields,
            $this->fieldItem('textarea', 'notes', 'Notes', false, ['rows' => 6, 'max_length' => 500, 'hint' => ['en' => 'Anything else?', 'ar' => '']]),
        ]]);
        $fields = collect($form->fresh()->fields)->keyBy(fn (array $item): string => $item['data']['key']);
        $fields['passport_copy'] = array_replace_recursive($fields['passport_copy'], ['data' => ['max_size' => '3000']]);
        $form->update(['fields' => $fields->values()->all()]);

        $this->editor($form)
            ->assertSee(__('packstub-form-builder::form-builder.editor.more_options'))
            ->assertSee('2.93 MB')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = collect($form->fresh()->fields)->keyBy(fn (array $item): string => $item['data']['key']);
        $this->assertSame(['full_name', 'mobile', 'email', 'passport_copy', 'notes'], $saved->keys()->all());
        $this->assertSame(3000, (int) $saved['passport_copy']['data']['max_size']);
        $this->assertSame(['pdf'], $saved['passport_copy']['data']['accepted_types']);
        $this->assertSame(6, (int) $saved['notes']['data']['rows']);
        $this->assertSame(500, (int) $saved['notes']['data']['max_length']);
        $this->assertSame('Anything else?', $saved['notes']['data']['hint']['en']);
    }

    public function test_a_choice_value_is_filled_from_its_english_label_and_never_overwritten(): void
    {
        $form = $this->makeForm();
        $form->update(['fields' => [['type' => 'select', 'data' => [
            'key' => 'transport',
            'label' => ['en' => 'Transport', 'ar' => 'النقل'],
            'choices' => [
                ['value' => '', 'label' => ['en' => '', 'ar' => '']],
                ['value' => 'old_value', 'label' => ['en' => 'Old label', 'ar' => '']],
            ],
        ]]]]);

        $page = $this->editor($form);
        $item = $this->itemKey($page);
        [$new, $old] = array_keys($page->get("data.fields.{$item}.data.choices"));

        $page->set("data.fields.{$item}.data.choices.{$new}.label.en", 'Yes, by car')
            ->assertSet("data.fields.{$item}.data.choices.{$new}.value", 'yes_by_car')
            ->set("data.fields.{$item}.data.choices.{$old}.label.en", 'New label')
            ->assertSet("data.fields.{$item}.data.choices.{$old}.value", 'old_value');

        $this->assertSame('yes_by_car', SelectField::choiceValue('  Yes, by car '));
        $this->assertSame('naam', SelectField::choiceValue('نعم'));
        $this->assertSame('؟', SelectField::choiceValue('؟'));
    }

    public function test_file_sizes_are_offered_in_mb_and_keep_a_custom_size(): void
    {
        $options = FileField::maxSizeOptions();
        $this->assertSame('5 MB', $options[5120]);
        $this->assertSame('1 MB', $options[1024]);

        $this->assertSame('2.93 MB', FileField::maxSizeOptions('3000')[3000]);
        $this->assertArrayNotHasKey(0, FileField::maxSizeOptions('abc'));
    }

    public function test_every_template_is_complete_in_both_languages_and_valid(): void
    {
        foreach (FormTemplates::KEYS as $key) {
            $fields = FormTemplates::fields($key);
            $this->assertNotEmpty($fields, $key);

            foreach (['en', 'ar'] as $locale) {
                $this->assertStringNotContainsString('templates.', FormTemplates::name($key)[$locale], "{$key} {$locale} name");
            }

            $keys = [];

            foreach ($fields as $item) {
                $this->assertNotNull(app(FieldTypeRegistry::class)->find($item['type']), "{$key}: {$item['type']}");

                foreach (['en', 'ar'] as $locale) {
                    $label = $item['data']['label'][$locale];
                    $this->assertNotSame('', $label);
                    $this->assertStringNotContainsString('template_fields.', $label, "{$key}.{$item['data']['key']} {$locale}");

                    foreach ($item['data']['choices'] ?? [] as $choice) {
                        $this->assertStringNotContainsString('template_choices.', $choice['label'][$locale]);
                    }
                }

                $keys[] = $item['data']['key'];
            }

            $this->assertSame($keys, array_unique($keys), "{$key} has unique keys");
        }

        $this->assertSame(array_keys(FormTemplates::options()), FormTemplates::KEYS);
        $this->assertSame(array_keys(FormTemplates::descriptions()), FormTemplates::KEYS);
    }

    public function test_a_new_form_can_start_from_a_template(): void
    {
        Livewire::test(CreateForm::class)
            ->callAction('template', ['template' => 'feedback'])
            ->assertHasNoActionErrors()
            ->assertSet('data.name.en', 'Feedback')
            ->assertSet('data.slug', 'feedback')
            ->call('create')
            ->assertHasNoFormErrors();

        $form = Form::query()->where('slug', 'feedback')->firstOrFail();
        $this->assertSame(['rating', 'liked', 'improve', 'contact_me', 'contact_email'], array_column(array_column($form->fields, 'data'), 'key'));
        $this->assertSame('رأيك يهمنا', $form->getTranslation('name', 'ar'));
        $this->assertSame('كيف كانت تجربتك؟', $form->fields[0]['data']['label']['ar']);
        $this->assertSame(['field' => 'contact_me', 'operator' => 'filled', 'value' => null], $form->fieldList()->last()->condition());

        // a name already typed is kept
        Livewire::test(CreateForm::class)
            ->set('data.name.en', 'Stable visit')
            ->callAction('template', ['template' => 'booking_request'])
            ->assertSet('data.name.en', 'Stable visit')
            ->assertCount('data.fields', 7);
    }

    public function test_the_language_switch_hides_a_language_in_the_browser_only(): void
    {
        $form = $this->makeForm();

        $page = $this->editor($form)
            ->assertSeeHtml('fbEditorLang')
            ->assertSeeHtml('fb-lang-grid')
            ->assertSeeHtml(EditorLanguages::visibleJs('ar'));

        // nothing is hidden on the server: both languages are saved as they were
        $page->set('data.name.ar', 'طلب جواز معدل')->call('save')->assertHasNoFormErrors();
        $this->assertSame('طلب جواز معدل', $form->fresh()->getTranslation('name', 'ar'));
        $this->assertSame('Passport request', $form->fresh()->getTranslation('name', 'en'));
    }

    private function editor(Form $form): Testable
    {
        return Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])->assertSuccessful();
    }

    private function itemKey(Testable $page, int $index = 0): string
    {
        return array_keys($page->get('data.fields'))[$index];
    }
}
