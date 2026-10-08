<?php

namespace Tests\Feature;

use App\Support\FormOrderSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Livewire\FormBuilderForm;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Support\FieldConditions;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/**
 * "Show this field only when …": the server applies the rules (a hidden field is not
 * validated and is stored empty), both renderers mirror them, and the editor only lets
 * a field depend on one before it.
 */
class FormFieldConditionsTest extends TestCase
{
    use MakesOrderForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();
    }

    private function makeTripForm(): Form
    {
        $choices = [
            ['value' => 'no', 'label' => ['en' => 'No', 'ar' => 'لا']],
            ['value' => 'yes', 'label' => ['en' => 'Yes', 'ar' => 'نعم']],
        ];

        return Form::create([
            'name' => ['en' => 'Trip', 'ar' => 'رحلة'],
            'slug' => 'trip',
            'fields' => [
                $this->fieldItem('text', 'full_name', 'Full name'),
                $this->fieldItem('radio', 'travelled', 'Did you travel?', true, ['choices' => $choices]),
                $this->fieldItem('text', 'destination', 'Where to?', true, ['condition' => ['field' => 'travelled', 'operator' => 'is', 'choice' => 'yes']]),
                $this->fieldItem('text', 'airline', 'Which airline?', true, ['condition' => ['field' => 'destination', 'operator' => 'filled']]),
                $this->fieldItem('checkbox', 'newsletter', 'Send me news', false),
                $this->fieldItem('email', 'news_email', 'Email for news', true, ['condition' => ['field' => 'newsletter', 'operator' => 'filled']]),
            ],
            'settings' => ['min_seconds' => 0],
        ]);
    }

    private function submit(array $input)
    {
        return $this->postJson(route('packstub-form-builder.submit', 'trip'), $input + ['full_name' => 'Ali']);
    }

    public function test_a_hidden_field_is_not_asked_for_and_is_stored_empty(): void
    {
        $this->makeTripForm();

        $this->submit(['travelled' => 'no', 'destination' => 'Paris', 'airline' => 'Oman Air'])->assertOk();

        $data = FormSubmission::query()->latest('id')->first()->data;
        $this->assertSame('no', $data['travelled']);
        $this->assertNull($data['destination']);
        $this->assertNull($data['airline']);
        $this->assertNull($data['news_email']);
    }

    public function test_a_shown_field_is_validated_as_usual(): void
    {
        $this->makeTripForm();

        $this->submit(['travelled' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors(['destination']);
        $this->submit(['travelled' => 'yes', 'destination' => 'Paris'])->assertUnprocessable()->assertJsonValidationErrors(['airline']);
        $this->submit(['travelled' => 'yes', 'destination' => 'Paris', 'airline' => 'Oman Air'])->assertOk();
        $this->submit(['travelled' => 'no', 'newsletter' => '1'])->assertUnprocessable()->assertJsonValidationErrors(['news_email']);

        $this->assertSame(1, FormSubmission::query()->count());
        $this->assertSame('Paris', FormSubmission::query()->first()->data['destination']);
    }

    public function test_rules_chain_and_a_hidden_field_counts_as_unanswered(): void
    {
        $form = $this->makeTripForm();

        // "airline" depends on "destination", hidden here, so it is hidden too even though a value was sent
        $visible = FieldConditions::visibleKeys($form, ['travelled' => 'no', 'destination' => 'Paris']);

        $this->assertContains('travelled', $visible);
        $this->assertNotContains('destination', $visible);
        $this->assertNotContains('airline', $visible);
        $this->assertNotContains('news_email', $visible);

        $this->assertContains('airline', FieldConditions::visibleKeys($form, ['travelled' => 'yes', 'destination' => 'Paris']));
        $this->assertContains('news_email', FieldConditions::visibleKeys($form, ['newsletter' => true]));
        $this->assertNotContains('news_email', FieldConditions::visibleKeys($form, ['newsletter' => false]));
    }

    public function test_the_operators(): void
    {
        $rule = fn (string $operator, ?string $value = null): array => ['field' => 'x', 'operator' => $operator, 'value' => $value];

        $this->assertTrue(FieldConditions::holds($rule('is', 'a'), ['a', 'b']));
        $this->assertFalse(FieldConditions::holds($rule('is', 'c'), ['a', 'b']));
        $this->assertTrue(FieldConditions::holds($rule('is_not', 'c'), ['a']));
        $this->assertTrue(FieldConditions::holds($rule('is_not', 'c'), []));
        $this->assertTrue(FieldConditions::holds($rule('filled'), ['a']));
        $this->assertFalse(FieldConditions::holds($rule('filled'), []));
        $this->assertTrue(FieldConditions::holds($rule('empty'), []));

        // an incomplete rule is no rule
        $this->assertNull(FieldConditions::rule(['condition' => ['field' => 'x', 'operator' => 'is']]));
        $this->assertNull(FieldConditions::rule(['condition' => ['field' => '']]));
        $this->assertSame('yes', FieldConditions::rule(['condition' => ['field' => 'x', 'operator' => 'is', 'choice' => 'yes', 'value' => 'stale']])['value']);
    }

    public function test_the_plain_renderer_carries_the_rules_for_its_script(): void
    {
        $this->makeTripForm();

        $html = Blade::render('<x-form-builder::form form="trip" />');

        $this->assertStringContainsString('data-fb-field="destination" data-fb-show-when="'.e(json_encode(['field' => 'travelled', 'operator' => 'is', 'value' => 'yes'])).'"', $html);
        $this->assertStringNotContainsString('data-fb-field="full_name" data-fb-show-when', $html);
        $this->assertStringContainsString('initRules', $html);

        $definition = $this->getJson(route('packstub-form-builder.definition', 'trip'))->assertOk()->json('fields');
        $this->assertSame(['field' => 'travelled', 'operator' => 'is', 'value' => 'yes'], collect($definition)->firstWhere('key', 'destination')['condition']);
        $this->assertNull(collect($definition)->firstWhere('key', 'full_name')['condition']);
    }

    public function test_the_livewire_renderer_shows_and_hides_fields_as_answers_change(): void
    {
        $this->makeTripForm();

        Livewire::test(FormBuilderForm::class, ['form' => 'trip'])
            ->assertDontSee('Where to?')
            ->set('data.travelled', 'yes')
            ->assertSee('Where to?')
            ->assertDontSee('Which airline?')
            ->set('data.full_name', 'Ali')
            ->call('submit')
            ->assertHasErrors(['data.destination'])
            ->set('data.travelled', 'no')
            ->assertDontSee('Where to?')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);
    }

    public function test_a_conditional_field_is_never_offered_as_the_customers_contact(): void
    {
        $items = $this->makeTripForm()->fields;

        // "destination" and "airline" are text fields too, but conditional; "news_email" is the only email field
        $this->assertSame(['full_name'], array_keys(FormOrderSettings::fieldOptions($items, ['text'])));
        $this->assertSame([], FormOrderSettings::fieldOptions($items, ['email']));
    }

    public function test_the_editor_offers_only_earlier_fields_and_refuses_a_broken_rule(): void
    {
        $form = $this->makeTripForm();

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Shown when “Did you travel?” is “Yes”', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $items = $page->get('data.fields');
        $keys = array_keys($items);
        $this->assertSame(['full_name', 'travelled'], array_keys(FieldConditions::sourcesBefore($items, $keys[2])));
        // a tick box can drive a rule; a field cannot depend on itself or on a later one
        $this->assertArrayHasKey('newsletter', FieldConditions::sourcesBefore($items, $keys[5]));
        $this->assertArrayNotHasKey('news_email', FieldConditions::sourcesBefore($items, $keys[5]));

        // move "destination" above "travelled": its rule now points at a later field
        [$a, $b, $c] = $keys;
        $reordered = [$a => $items[$a], $c => $items[$c], $b => $items[$b]] + $items;

        $page->set('data.fields', $reordered)
            ->call('save')
            ->assertHasFormErrors(['fields']);

        $this->assertSame('travelled', $form->fresh()->fields[1]['data']['key']);
    }
}
