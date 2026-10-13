<?php

namespace Tests\Feature\Api\V1;

use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi(['currency_code' => 'OMR', 'currency_symbol' => 'OMR']);
        $this->prepareDirectoryTables();
        $this->ids = $this->seedLocations();
    }

    public function test_farriers_list_filters_and_show_like_the_website(): void
    {
        $cheap = $this->farrier(['en_name' => 'Ali', 'specialty' => 'Corrective shoeing', 'price' => 15]);
        $dear = $this->farrier(['en_name' => 'Salim', 'specialty' => 'Barefoot trimming', 'price' => 40, 'city_id' => $this->ids['bawshar']]);
        $this->farrier(['en_name' => 'Gone', 'status' => 'inactive']);

        $this->getJson('/api/v1/farriers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Salim')
            ->assertJsonPath('data.1.price', ['amount' => '15.000', 'currency' => ['code' => 'OMR', 'symbol' => 'OMR', 'icon_url' => null]])
            ->assertJsonPath('data.1.specialty', 'Corrective shoeing');

        $this->assertSame([$cheap], $this->getJson('/api/v1/farriers?specialty=corrective')->json('data.*.id'));
        $this->assertSame([$dear], $this->getJson('/api/v1/farriers?city_id='.$this->ids['bawshar'])->json('data.*.id'));
        $this->assertSame([$cheap, $dear], $this->getJson('/api/v1/farriers?sort=price_asc')->json('data.*.id'));

        $this->getJson("/api/v1/farriers/{$cheap}", ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.name', 'علي')
            ->assertJsonPath('data.description', 'خبرة طويلة.')
            ->assertJsonPath('data.region.name', 'مسقط')
            ->assertJsonPath('data.years_experience', 12)
            ->assertJsonPath('data.share_url', route('farriers.show', $cheap));
    }

    public function test_the_transfer_board_hides_past_dates_and_filters_like_the_website(): void
    {
        $soon = $this->transfer(['transfer_date' => now()->addDay()->toDateString(), 'price' => 80]);
        $later = $this->transfer(['transfer_date' => now()->addDays(5)->toDateString(), 'price' => 30, 'capacity' => 4]);
        $request = $this->transfer(['type' => 'request', 'transfer_date' => now()->addDays(2)->toDateString(), 'price' => null]);
        $this->transfer(['transfer_date' => now()->subDay()->toDateString()]);

        $this->getJson('/api/v1/transfer-board')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$soon, $request, $later])
            ->assertJsonPath('data.0.type.value', 'offer')
            ->assertJsonPath('data.0.from.city.name', 'Seeb')
            ->assertJsonPath('data.0.to.city.name', 'Bawshar')
            ->assertJsonPath('data.1.price', null);

        $this->assertSame([$request], $this->getJson('/api/v1/transfer-board?type=request')->json('data.*.id'));
        $this->assertSame([$later], $this->getJson('/api/v1/transfer-board?capacity_min=3')->json('data.*.id'));
        $this->assertSame([$later], $this->getJson('/api/v1/transfer-board?type=offer&price_max=50')->json('data.*.id'));
        // A price limit means nothing for requests.
        $this->assertSame([$request], $this->getJson('/api/v1/transfer-board?type=request&price_max=1')->json('data.*.id'));
        $this->assertSame([$later, $soon, $request], $this->getJson('/api/v1/transfer-board?sort=price_asc')->json('data.*.id'));

        $this->getJson("/api/v1/transfer-board/{$soon}")->assertOk()
            ->assertJsonPath('data.from.region.name', 'Muscat')
            ->assertJsonPath('data.share_url', route('transfer-board.show', $soon));
        $this->getJson('/api/v1/transfer-board/'.($request + 1))->assertNotFound();
    }

    public function test_places_list_only_active_ones_and_filter_by_type_service_and_place(): void
    {
        $vet = $this->row('clinic_services', ['en_name' => 'Dentistry', 'ar_name' => 'طب الأسنان', 'applies_to' => 'clinic']);
        $clinic = $this->place('clinics', ['en_name' => 'Al Noor Clinic', 'slug' => 'al-noor', 'type' => 'clinic']);
        $pharmacy = $this->place('clinics', ['en_name' => 'Barka Pharmacy', 'slug' => 'barka', 'type' => 'pharmacy', 'city_id' => $this->ids['bawshar']]);
        $this->place('clinics', ['en_name' => 'Closed Clinic', 'slug' => 'closed', 'is_active' => false]);
        $this->row('clinic_clinic_service', ['clinic_id' => $clinic, 'clinic_service_id' => $vet]);

        $this->getJson('/api/v1/clinics')
            ->assertOk()
            ->assertJsonPath('data.*.slug', ['al-noor', 'barka'])
            ->assertJsonPath('data.0.type', ['value' => 'clinic', 'label' => __('clinics.types.clinic', [], 'en')])
            ->assertJsonPath('data.0.services.0.name', 'Dentistry');

        $this->assertSame(['barka'], $this->getJson('/api/v1/clinics?type=pharmacy')->json('data.*.slug'));
        $this->assertSame(['al-noor'], $this->getJson("/api/v1/clinics?service_id={$vet}")->json('data.*.slug'));
        $this->assertSame(['barka'], $this->getJson('/api/v1/clinics?city_id='.$this->ids['bawshar'])->json('data.*.slug'));
        $this->assertSame(['barka', 'al-noor'], $this->getJson('/api/v1/clinics?sort=newest')->json('data.*.slug'));
        $this->getJson('/api/v1/clinics?type=stable')->assertUnprocessable();
        $this->getJson('/api/v1/clinics/closed')->assertNotFound();
        $this->assertNotNull($pharmacy);
    }

    public function test_a_place_page_has_hours_contact_and_services_and_shops_their_payment_options(): void
    {
        $this->place('shops', [
            'en_name' => 'Tack Shop', 'ar_name' => 'متجر العدة', 'slug' => 'tack', 'type' => 'tack',
            'phone' => '24000000', 'website_url' => 'https://tack.example', 'gallery' => json_encode(['shops/a.jpg']),
            'opening_hours' => json_encode(['mon' => ['closed' => false, 'opens_at' => '09:00', 'closes_at' => '17:00'], 'tue' => ['closed' => true]]),
            'is_online' => true, 'delivery_scope' => 'local', 'payment_options' => json_encode(['cash', 'card']),
            'en_description' => 'Saddles and bridles.', 'ar_description' => 'سروج ولجام.',
        ]);

        $response = $this->getJson('/api/v1/shops/tack', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.name', 'متجر العدة')
            ->assertJsonPath('data.description', 'سروج ولجام.')
            ->assertJsonPath('data.region.name', 'مسقط')
            ->assertJsonPath('data.gallery_urls', [url('/storage/shops/a.jpg')])
            ->assertJsonPath('data.opening_hours.0', ['day' => 'mon', 'label' => __('shops.days.mon', [], 'ar'), 'closed' => false, 'opens_at' => '09:00', 'closes_at' => '17:00'])
            ->assertJsonPath('data.opening_hours.1.closed', true)
            ->assertJsonPath('data.opening_hours.6.day', 'sun')
            ->assertJsonPath('data.is_online', true)
            ->assertJsonPath('data.delivery_scope.value', 'local')
            ->assertJsonPath('data.payment_options.1', ['value' => 'card', 'label' => __('shops.payment_options_list.card', [], 'ar')])
            ->assertJsonPath('data.rating', null)
            ->assertJsonPath('data.views_count', 0)
            ->assertJsonPath('data.share_url', route('shops.show', 'tack'));

        $this->assertCount(7, $response->json('data.opening_hours'));
    }

    public function test_stables_show_only_approved_ones_with_their_rating_and_visible_reviews(): void
    {
        $stable = $this->place('stables', ['en_name' => 'Royal Stables', 'slug' => 'royal', 'email' => 'royal@example.com']);
        $this->place('stables', ['en_name' => 'Pending Stables', 'slug' => 'pending', 'approval_status' => 'pending']);
        $this->row('stable_reviews', ['stable_id' => $stable, 'author_name' => 'Aisha Al Balushi', 'rating' => 5, 'comment' => 'Lovely lessons', 'reply' => 'Thank you!']);
        $this->row('stable_reviews', ['stable_id' => $stable, 'author_name' => 'Omar', 'rating' => 4]);
        $this->row('stable_reviews', ['stable_id' => $stable, 'author_name' => 'Hidden', 'rating' => 1, 'is_visible' => false]);

        $this->assertSame(['royal'], $this->getJson('/api/v1/stables')->json('data.*.slug'));
        $this->getJson('/api/v1/stables?type=clinic')->assertUnprocessable();
        $this->getJson('/api/v1/stables/pending')->assertNotFound();

        $this->getJson('/api/v1/stables/royal')
            ->assertOk()
            ->assertJsonPath('data.type', null)
            ->assertJsonPath('data.email', 'royal@example.com')
            ->assertJsonPath('data.rating', ['average' => 4.5, 'count' => 2])
            ->assertJsonCount(2, 'data.reviews')
            ->assertJsonPath('data.reviews.1.author', 'Aisha A.')
            ->assertJsonPath('data.reviews.1.reply', 'Thank you!')
            ->assertJsonPath('data.views_count', null)
            ->assertJsonPath('data.share_url', route('stables.show', 'royal'));
    }

    public function test_lookups_carry_the_directory_choices(): void
    {
        $this->row('shop_services', ['en_name' => 'Delivery', 'ar_name' => 'توصيل', 'applies_to' => 'all']);
        $this->row('event_categories', ['en_name' => 'Racing', 'ar_name' => 'سباقات', 'color' => '#ff0000']);

        $this->getJson('/api/v1/lookups')
            ->assertOk()
            ->assertJsonPath('data.transfer_types.0.value', 'offer')
            ->assertJsonPath('data.clinic_types.1.value', 'pharmacy')
            ->assertJsonPath('data.center_types.0.value', 'training')
            ->assertJsonPath('data.shop_types.0', ['value' => 'tack', 'label' => __('shops.types.tack', [], 'en')])
            ->assertJsonPath('data.shop_services.0', ['id' => 1, 'name' => 'Delivery', 'applies_to' => 'all'])
            ->assertJsonPath('data.stable_services', [])
            ->assertJsonPath('data.event_categories.0', ['id' => 1, 'name' => 'Racing', 'color' => '#ff0000']);
    }

    private function farrier(array $values): int
    {
        return $this->row('farriers', $values + [
            'ar_name' => 'علي', 'years_experience' => 12, 'country_id' => $this->ids['oman'], 'region_id' => $this->ids['muscat'],
            'city_id' => $this->ids['seeb'], 'contact_number' => '99887766', 'status' => 'active',
            'description_en' => 'Long experience.', 'description_ar' => 'خبرة طويلة.',
        ]);
    }

    private function transfer(array $values): int
    {
        return $this->row('transfer_posts', $values + [
            'type' => 'offer', 'capacity' => 2, 'contact_number' => '99887766', 'status' => 'active',
            'from_country_id' => $this->ids['oman'], 'from_region_id' => $this->ids['muscat'], 'from_city_id' => $this->ids['seeb'],
            'to_country_id' => $this->ids['oman'], 'to_region_id' => $this->ids['muscat'], 'to_city_id' => $this->ids['bawshar'],
        ]);
    }

    private function place(string $table, array $values): int
    {
        return $this->row($table, $values + [
            'country_id' => $this->ids['oman'], 'region_id' => $this->ids['muscat'], 'city_id' => $this->ids['seeb'], 'is_active' => true,
        ]);
    }
}
