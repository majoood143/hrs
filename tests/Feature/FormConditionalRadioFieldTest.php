<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Http\FormState;
use Packstub\FormBuilder\Livewire\FormBuilderForm;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/**
 * The "conditional_radio" field type: radio buttons with a details box that only the
 * revealing answers ask for, stored under one key as {answer, details}.
 */
class FormConditionalRadioFieldTest extends TestCase
{
    use MakesOrderForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();
    }

    private function makeTravelForm(bool $required = true, array $extra = []): Form
    {
        return Form::create([
            'name' => ['en' => 'Registration', 'ar' => 'تسجيل'],
            'slug' => 'travel',
            'fields' => [
                $this->fieldItem('text', 'full_name', 'Full name'),
                $this->fieldItem('phone', 'mobile', 'Mobile'),
                $this->fieldItem('email', 'email', 'Email'),
                $this->fieldItem('conditional_radio', 'travelled', 'Did you travel last year?', $required, $extra + [
                    'choices' => [
                        ['value' => 'no', 'label' => ['en' => 'No', 'ar' => 'لا']],
                        ['value' => 'yes', 'label' => ['en' => 'Yes', 'ar' => 'نعم']],
                    ],
                    'reveal_on' => ['yes'],
                    'details_label' => ['en' => 'Where to?', 'ar' => 'إلى أين؟'],
                    'details_required' => true,
                ]),
            ],
            'settings' => ['min_seconds' => 0],
        ]);
    }

    private function submit(array $travelled)
    {
        return $this->postJson(route('packstub-form-builder.submit', 'travel'), $this->customerInput(['travelled' => $travelled]));
    }

    public function test_yes_with_details_is_stored_and_formatted_together(): void
    {
        $this->makeTravelForm();

        $this->submit(['answer' => 'yes', 'details' => ' Italy '])->assertOk();

        $submission = FormSubmission::firstOrFail();
        $this->assertSame(['answer' => 'yes', 'details' => 'Italy'], $submission->value('travelled'));
        $this->assertSame('Yes — Italy', $submission->formatted()['travelled']['value']);
    }

    public function test_yes_without_details_is_rejected(): void
    {
        $this->makeTravelForm();

        $this->submit(['answer' => 'yes', 'details' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['travelled.details'], 'errors');

        $this->assertSame(0, FormSubmission::count());
    }

    public function test_details_left_behind_by_a_non_revealing_answer_are_dropped(): void
    {
        $this->makeTravelForm();

        $this->submit(['answer' => 'no', 'details' => 'Italy'])->assertOk();

        $submission = FormSubmission::firstOrFail();
        $this->assertSame(['answer' => 'no', 'details' => null], $submission->value('travelled'));
        $this->assertSame('No', $submission->formatted()['travelled']['value']);
    }

    public function test_the_answer_must_be_one_of_the_choices_and_is_required_when_the_field_is(): void
    {
        $this->makeTravelForm();

        $this->submit(['answer' => 'maybe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['travelled.answer'], 'errors');

        $this->submit(['details' => 'Italy'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['travelled.answer'], 'errors');
    }

    public function test_an_optional_field_left_unanswered_stores_nothing(): void
    {
        $this->makeTravelForm(required: false);

        $this->submit(['details' => ''])->assertOk();

        $this->assertNull(FormSubmission::firstOrFail()->value('travelled'));
    }

    public function test_the_plain_renderer_posts_both_parts_and_marks_the_revealing_answer(): void
    {
        $this->makeTravelForm();

        $html = Blade::render('<x-form-builder::form :form="$slug" />', ['slug' => 'travel']);

        $this->assertStringContainsString('data-fb-conditional', $html);
        $this->assertMatchesRegularExpression('/name="travelled\[answer\]" value="yes"[^>]*data-fb-reveals/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="travelled\[answer\]" value="no"[^>]*data-fb-reveals/', $html);
        $this->assertStringContainsString('name="travelled[details]"', $html);
        $this->assertStringContainsString('Where to?', $html);
        // A hidden box must never carry a plain "required" (the browser would refuse to submit).
        $this->assertDoesNotMatchRegularExpression('/name="travelled\[details\]"[^>]*\srequired[\s>]/', $html);
        $this->assertStringContainsString('initConditional', $html);
    }

    public function test_an_error_on_a_part_shows_under_the_field(): void
    {
        $state = new FormState(errors: ['travelled.details' => ['Where to? is required.']]);

        $this->assertSame('Where to? is required.', $state->error('travelled'));
        $this->assertNull($state->error('travel'));
    }

    public function test_the_livewire_renderer_reveals_and_requires_the_details(): void
    {
        $this->makeTravelForm();

        Livewire::test(FormBuilderForm::class, ['form' => 'travel'])
            ->assertDontSee('Where to?')
            ->set('data.travelled.answer', 'yes')
            ->assertSee('Where to?')
            ->set('data.full_name', 'Ali Al Balushi')
            ->set('data.mobile', '9123 4567')
            ->set('data.email', 'ali@example.com')
            ->call('submit')
            ->assertHasErrors(['data.travelled.details'])
            ->set('data.travelled.details', 'Italy')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(['answer' => 'yes', 'details' => 'Italy'], FormSubmission::firstOrFail()->value('travelled'));
    }

    public function test_the_builder_block_offers_the_choices_as_triggers_and_saves(): void
    {
        $form = $this->makeTravelForm();

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSuccessful()
            ->assertSee(__('packstub-form-builder::form-builder.editor.reveal_on'))
            ->assertSee(__('packstub-form-builder::form-builder.editor.details_label'))
            ->call('save')
            ->assertHasNoFormErrors();

        $field = collect($form->fresh()->fields)->firstWhere('type', 'conditional_radio');
        $this->assertSame(['yes'], $field['data']['reveal_on']);
        $this->assertSame('Where to?', $field['data']['details_label']['en']);
    }
}
