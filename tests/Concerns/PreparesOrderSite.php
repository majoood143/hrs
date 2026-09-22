<?php

namespace Tests\Concerns;

use App\Models\CommissionSetting;
use App\Models\Service;
use App\Models\ServiceFeeSetting;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Orders\CreateServiceOrder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Tables and helpers for the service order / payment tests. The order tables come from the real
 * migrations (they are portable); `services` is created by hand because its migration history
 * is MySQL-only.
 */
trait PreparesOrderSite
{
    use PreparesSiteLayout;

    protected function prepareOrderSite(array $settings = []): void
    {
        $this->prepareSiteLayout();

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ar_name')->nullable();
            $table->text('description')->nullable();
            $table->text('ar_description')->nullable();
            $table->decimal('price', 10, 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach ([
            // ServiceOrder's LogsActivity trait writes here on every create/update.
            '2025_10_03_170348_create_activity_log_table',
            '2025_10_03_170349_add_event_column_to_activity_log_table',
            '2025_10_03_170350_add_batch_uuid_column_to_activity_log_table',
            '2026_09_22_000002_create_service_orders_table',
            '2026_09_22_000003_create_service_fee_settings_table',
            '2026_09_23_000002_create_notification_logs_table',
            '2026_09_24_000001_create_commission_settings_and_order_commission_columns',
            '2026_09_25_000001_create_order_review_tables',
            '2026_09_26_000001_add_vat_on_commission_to_service_orders',
            '2026_09_27_000001_add_agent_details_to_service_orders',
            '2026_09_28_000001_add_unique_submission_id_to_service_orders',
            '2026_09_28_000002_create_payment_gateway_sessions_table',
            '2026_09_28_000003_add_requires_document_to_service_orders',
            '2026_09_29_000001_add_outcome_to_payment_gateway_logs',
            '2026_09_29_000002_add_status_created_at_index_to_service_orders',
        ] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }

        $this->seedSiteSettings($settings);
    }

    /**
     * Site settings for this test, without a site_settings table: SiteSetting reads its whole
     * table from one cached collection.
     *
     * @param  array<string, mixed>  $settings  key => value (booleans become "boolean" settings)
     */
    protected function seedSiteSettings(array $settings): void
    {
        Cache::forever('site_settings.all', collect($settings)->map(fn ($value, $key) => (object) [
            'key' => $key,
            'type' => is_bool($value) ? 'boolean' : 'text',
            'value' => is_bool($value) ? ($value ? 'true' : 'false') : (is_array($value) ? json_encode($value) : (string) $value),
        ]));

        // SiteSetting::cached() memoizes in a static property for the process's lifetime; a test
        // reseeding settings mid-test (many do, to simulate an admin changing one) must not keep
        // serving whatever an earlier SiteSetting::get() call in the same test already cached.
        SiteSetting::resetMemo();
    }

    protected function makeService(float $price = 10.0, string $name = 'Horse passport'): Service
    {
        return Service::create(['name' => $name, 'ar_name' => 'جواز حصان', 'price' => $price, 'is_active' => true]);
    }

    protected function makeCommission(string $type = 'percentage', float $value = 10.0, array $scope = [], array $extra = []): CommissionSetting
    {
        return CommissionSetting::create($scope + $extra + ['commission_type' => $type, 'commission_value' => $value, 'name' => ['en' => 'Commission', 'ar' => 'عمولة'], 'is_active' => true]);
    }

    protected function makeFee(string $type = 'percentage', float $value = 5.0, array $scope = [], array $extra = []): ServiceFeeSetting
    {
        return ServiceFeeSetting::create($scope + $extra + ['fee_type' => $type, 'fee_value' => $value, 'name' => ['en' => 'Fee', 'ar' => 'رسوم'], 'is_active' => true]);
    }

    /** A pending-payment order for a 10.000 OMR service with a 5% fee and 5% VAT: total 11.025. */
    protected function makeOrder(float $price = 10.0, ?float $feePercent = 5.0, array $customer = []): ServiceOrder
    {
        $service = $this->makeService($price);

        if ($feePercent !== null) {
            $this->makeFee('percentage', $feePercent);
        }

        return app(CreateServiceOrder::class)->handle($service, $customer + [
            'name' => 'Ali Al Balushi',
            'email' => 'ali@example.com',
            'phone' => '96891234567',
            'locale' => 'en',
        ]);
    }
}
