<?php

namespace Tests\Feature;

use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Models\FormSubmission;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/**
 * "Only one answer per value" (a single-line field's `unique` option): a submission repeating a value
 * an earlier one of the same form gave is refused, compared the way the type spells it.
 */
class FormUniqueAnswerTest extends TestCase
{
    use MakesOrderForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();
    }

    public function test_an_email_already_used_is_refused_whatever_its_case(): void
    {
        $form = $this->makeForm();
        $fields = $form->fields;
        $fields[2]['data']['unique'] = true;
        $form->update(['fields' => $fields]);

        $this->postJson(route('packstub-form-builder.submit', 'passport'), $this->customerInput())->assertOk();
        $this->postJson(route('packstub-form-builder.submit', 'passport'), $this->customerInput(['email' => ' ALI@example.com ', 'mobile' => '9999 0000']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email'], 'errors');

        // the same mobile is fine: only the email is unique, and only within this form
        $this->postJson(route('packstub-form-builder.submit', 'passport'), $this->customerInput(['email' => 'sara@example.com']))->assertOk();
        $this->makeForm(slug: 'other');
        $this->postJson(route('packstub-form-builder.submit', 'other'), $this->customerInput())->assertOk();

        $this->assertSame(3, FormSubmission::count());
    }

    public function test_only_single_line_types_offer_it(): void
    {
        $registry = app(FieldTypeRegistry::class);

        foreach (['text', 'email', 'phone', 'number', 'url'] as $id) {
            $this->assertTrue($registry->get($id)->canBeUnique(), $id);
        }

        foreach (['select', 'radio', 'checkboxes', 'file', 'textarea', 'race_winner'] as $id) {
            $this->assertFalse($registry->get($id)->canBeUnique(), $id);
        }
    }
}
