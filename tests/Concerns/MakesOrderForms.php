<?php

namespace Tests\Concerns;

use App\Models\Service;
use Packstub\FormBuilder\Models\Form;

/**
 * A form-builder form (name, phone, email, optionally a file) linked or not to a service.
 * The time trap is off so a test can post without first rendering the form.
 */
trait MakesOrderForms
{
    use PreparesOrderSite;

    protected function prepareFormSite(array $settings = []): void
    {
        $this->prepareOrderSite($settings + ['enabled_gateways' => ['demo']]);

        (require base_path('packages/packstub/filament-form-builder/database/migrations/create_form_builder_tables.php.stub'))->up();
    }

    protected function fieldItem(string $type, string $key, string $label, bool $required = true, array $extra = []): array
    {
        return ['type' => $type, 'data' => ['key' => $key, 'label' => ['en' => $label, 'ar' => $label], 'required' => $required] + $extra];
    }

    protected function makeForm(?Service $service = null, array $settings = [], bool $withFile = false, string $slug = 'passport'): Form
    {
        $fields = [
            $this->fieldItem('text', 'full_name', 'Full name'),
            $this->fieldItem('phone', 'mobile', 'Mobile'),
            $this->fieldItem('email', 'email', 'Email'),
        ];

        if ($withFile) {
            $fields[] = $this->fieldItem('file', 'passport_copy', 'Passport copy', false, ['accepted_types' => ['pdf']]);
        }

        return Form::create([
            'name' => ['en' => 'Passport request', 'ar' => 'طلب جواز'],
            'slug' => $slug,
            'fields' => $fields,
            'settings' => array_replace_recursive(
                ['min_seconds' => 0, 'payment' => ['service_id' => $service?->getKey()]],
                $settings,
            ),
        ]);
    }

    /** @return array<string, string> */
    protected function customerInput(array $overrides = []): array
    {
        return $overrides + ['full_name' => 'Ali Al Balushi', 'mobile' => '9123 4567', 'email' => 'ali@example.com'];
    }
}
