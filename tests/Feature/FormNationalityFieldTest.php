<?php

namespace Tests\Feature;

use App\Models\Country;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Packstub\FormBuilder\Fields\Types\NationalityField;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/**
 * The "nationality" field type: a searchable dropdown sourced from the app's public Country
 * list (nationality_en/nationality_ar), not from builder-entered choices.
 */
class FormNationalityFieldTest extends TestCase
{
    use MakesOrderForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();

        foreach ([
            '2025_08_13_201740_create_countries_table',
            '2026_09_07_121818_add_is_public_to_countries_table',
            '2026_09_13_000000_add_country_code_and_phone_code_to_countries_table',
            '2026_09_13_000001_add_currency_nationality_and_order_to_countries_table',
        ] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }

        Country::create(['en_name' => 'Oman', 'ar_name' => 'عُمان', 'nationality_en' => 'Omani', 'nationality_ar' => 'عماني', 'is_public' => true, 'order' => 1]);
        Country::create(['en_name' => 'Egypt', 'ar_name' => 'مصر', 'nationality_en' => 'Egyptian', 'nationality_ar' => 'مصري', 'is_public' => true, 'order' => 2]);
        Country::create(['en_name' => 'Atlantis', 'ar_name' => 'أطلانطس', 'nationality_en' => 'Atlantean', 'nationality_ar' => 'أطلانطي', 'is_public' => false, 'order' => 0]);
    }

    private function makeNationalityForm(): Form
    {
        return Form::create([
            'name' => ['en' => 'Registration', 'ar' => 'تسجيل'],
            'slug' => 'passport',
            'fields' => [
                $this->fieldItem('text', 'full_name', 'Full name'),
                $this->fieldItem('phone', 'mobile', 'Mobile'),
                $this->fieldItem('email', 'email', 'Email'),
                $this->fieldItem('nationality', 'nationality', 'Nationality'),
            ],
            'settings' => ['min_seconds' => 0],
        ]);
    }

    public function test_only_public_countries_are_offered(): void
    {
        $this->makeNationalityForm();

        $html = Blade::render('<x-form-builder::form :form="$slug" />', ['slug' => 'passport']);

        $this->assertStringContainsString('Omani', $html);
        $this->assertStringContainsString('Egyptian', $html);
        $this->assertStringNotContainsString('Atlantean', $html);
    }

    public function test_the_plain_renderer_is_a_select_the_script_upgrades_into_a_searchable_combobox(): void
    {
        $this->makeNationalityForm();

        $html = Blade::render('<x-form-builder::form :form="$slug" />', ['slug' => 'passport']);

        $this->assertStringContainsString('data-fb-combobox', $html);
        $this->assertMatchesRegularExpression('/<select[^>]+name="nationality"/', $html);
        // The combobox JS (initCombobox) ships inside the inlined script and always anchors
        // its results panel below the field ("top:calc(100% ...") rather than letting the
        // browser's own popup flip upward and run off the top of the screen.
        $this->assertStringContainsString('initCombobox', $html);
    }

    public function test_a_public_nationality_is_accepted_and_stored(): void
    {
        $this->makeNationalityForm();

        $this->postJson(route('packstub-form-builder.submit', 'passport'), $this->customerInput(['nationality' => 'Omani']))
            ->assertOk();

        $this->assertSame('Omani', FormSubmission::firstOrFail()->value('nationality'));
    }

    public function test_a_nationality_outside_the_public_list_is_rejected(): void
    {
        $this->makeNationalityForm();

        $this->postJson(route('packstub-form-builder.submit', 'passport'), $this->customerInput(['nationality' => 'Atlantean']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nationality'], 'errors');

        $this->assertSame(0, FormSubmission::count());
    }

    public function test_choices_are_read_in_the_current_locale(): void
    {
        App::setLocale('ar');

        $choices = (new NationalityField)->fixedChoices();

        $this->assertArrayHasKey('عماني', $choices);
        $this->assertArrayNotHasKey('Omani', $choices);
    }
}
