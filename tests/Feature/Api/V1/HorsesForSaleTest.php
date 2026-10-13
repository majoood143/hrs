<?php

namespace Tests\Feature\Api\V1;

use App\Models\HorseSalePost;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

class HorsesForSaleTest extends TestCase
{
    use PreparesMobileApi;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi(['currency_code' => 'OMR', 'currency_symbol' => 'ر.ع.']);

        $this->ids = $this->seedLocations();
        $this->ids['arabian'] = DB::table('types')->insertGetId(['en_name' => 'Arabian', 'ar_name' => 'عربي']);
        $this->ids['thoroughbred'] = DB::table('types')->insertGetId(['en_name' => 'Thoroughbred', 'ar_name' => 'أصيل']);
        $this->ids['mare'] = DB::table('genders')->insertGetId(['en_name' => 'Mare', 'ar_name' => 'فرس']);
        $this->ids['bay'] = DB::table('colors')->insertGetId(['en_name' => 'Bay', 'ar_name' => 'أحمر', 'hex_code' => '#8b4513']);
    }

    public function test_the_list_shows_only_active_posts_newest_first_with_cards_ready_to_display(): void
    {
        $older = $this->horse(['en_name' => 'Older', 'price' => 900]);
        $newer = $this->horse(['en_name' => 'Newer', 'price' => '1250.5', 'price_negotiable' => true, 'dob' => now()->subYears(3)->subMonth()->toDateString()]);
        $this->horse(['en_name' => 'Sold one', 'status' => 'sold']);
        $this->horse(['en_name' => 'Expired one', 'status' => 'expired']);

        $this->getJson('/api/v1/horses-for-sale')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.0.name', 'Newer')
            ->assertJsonPath('data.0.type', ['id' => $this->ids['arabian'], 'name' => 'Arabian'])
            ->assertJsonPath('data.0.gender.name', 'Mare')
            ->assertJsonPath('data.0.city', ['id' => $this->ids['seeb'], 'name' => 'Seeb'])
            ->assertJsonPath('data.0.country.name', 'Oman')
            ->assertJsonPath('data.0.age_years', 3)
            ->assertJsonPath('data.0.price', [
                'amount' => '1250.500',
                'currency' => ['code' => 'OMR', 'symbol' => 'ر.ع.', 'icon_url' => null],
            ])
            ->assertJsonPath('data.0.price_negotiable', true)
            ->assertJsonPath('data.0.cover_photo_url', url('/storage/horse-sale/covers/newer.jpg'))
            ->assertJsonPath('data.0.contact_number', '99887766')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'last_page']]);
    }

    public function test_the_list_filters_like_the_website(): void
    {
        $bawsharArabian = $this->horse(['en_name' => 'Bawshar Arabian', 'city_id' => $this->ids['bawshar'], 'breed' => 'Straight Egyptian', 'price' => 500]);
        $seebArabian = $this->horse(['en_name' => 'Seeb Arabian', 'breed' => 'Polish', 'price' => 3000]);
        $thoroughbred = $this->horse(['en_name' => 'Thoroughbred', 'type_id' => $this->ids['thoroughbred'], 'price' => 1500]);

        $this->assertListIds(['type_id' => $this->ids['thoroughbred']], [$thoroughbred->id]);
        $this->assertListIds(['city_id' => $this->ids['bawshar']], [$bawsharArabian->id]);
        $this->assertListIds(['breed' => 'egypt'], [$bawsharArabian->id]);
        $this->assertListIds(['price_max' => 1500], [$thoroughbred->id, $bawsharArabian->id]);
        $this->assertListIds(['sort' => 'price_asc'], [$bawsharArabian->id, $thoroughbred->id, $seebArabian->id]);
        $this->assertListIds(['sort' => 'price_desc', 'region_id' => $this->ids['muscat']], [$seebArabian->id, $thoroughbred->id, $bawsharArabian->id]);

        $this->getJson('/api/v1/horses-for-sale?sort=cheapest')->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    public function test_the_list_pages_with_a_capped_page_size(): void
    {
        foreach (range(1, 3) as $n) {
            $this->horse(['en_name' => "Horse {$n}"]);
        }

        $this->getJson('/api/v1/horses-for-sale?per_page=2&page=2&type_id='.$this->ids['arabian'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Horse 1')
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('links.prev', url('/api/v1/horses-for-sale').'?per_page=2&type_id='.$this->ids['arabian'].'&page=1');

        $this->getJson('/api/v1/horses-for-sale?per_page=500')->assertOk()->assertJsonPath('meta.per_page', 50);
    }

    public function test_a_post_shows_everything_the_website_page_shows_in_the_requested_language(): void
    {
        $post = $this->horse([
            'color_id' => $this->ids['bay'],
            'dam' => 'Dam name',
            'sire' => 'Sire name',
            'birth_country_id' => $this->ids['oman'],
            'passport_number' => 'P-123',
            'passport_document' => 'horse-sale/passports/p.pdf',
            'images' => ['horse-sale/gallery/a.jpg', 'horse-sale/gallery/b.jpg'],
            'description_en' => 'Calm and fast.',
            'description_ar' => 'هادئة وسريعة.',
        ]);

        $this->getJson("/api/v1/horses-for-sale/{$post->id}", ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.name', 'حصان')
            ->assertJsonPath('data.description', 'هادئة وسريعة.')
            ->assertJsonPath('data.type.name', 'عربي')
            ->assertJsonPath('data.color', ['id' => $this->ids['bay'], 'name' => 'أحمر', 'hex' => '#8b4513'])
            ->assertJsonPath('data.region.name', 'مسقط')
            ->assertJsonPath('data.city.name', 'السيب')
            ->assertJsonPath('data.birth_country.name', 'عمان')
            ->assertJsonPath('data.dam', 'Dam name')
            ->assertJsonPath('data.passport_number', 'P-123')
            ->assertJsonPath('data.passport_document_url', url('/storage/horse-sale/passports/p.pdf'))
            ->assertJsonPath('data.image_urls', [
                url('/storage/horse-sale/covers/newer.jpg'),
                url('/storage/horse-sale/gallery/a.jpg'),
                url('/storage/horse-sale/gallery/b.jpg'),
            ])
            ->assertJsonPath('data.price.amount', '2000.000')
            ->assertJsonPath('data.share_url', route('horses-for-sale.show', $post->id));
    }

    public function test_a_post_that_is_not_active_or_does_not_exist_is_a_json_404(): void
    {
        $sold = $this->horse(['status' => 'sold']);

        $this->get("/api/v1/horses-for-sale/{$sold->id}")->assertNotFound()->assertHeader('Content-Type', 'application/json');
        $this->getJson('/api/v1/horses-for-sale/999')->assertNotFound();
    }

    public function test_viewing_through_the_api_does_not_count_views(): void
    {
        $post = $this->horse();

        $this->getJson("/api/v1/horses-for-sale/{$post->id}")->assertOk()->assertJsonPath('data.views_count', 0);

        $this->assertSame(0, (int) $post->fresh()->views_count);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<int>  $expected
     */
    private function assertListIds(array $query, array $expected): void
    {
        $ids = $this->getJson('/api/v1/horses-for-sale?'.http_build_query($query))->assertOk()->json('data.*.id');

        $this->assertSame($expected, $ids, 'filters: '.http_build_query($query));
    }

    private function horse(array $attributes = []): HorseSalePost
    {
        return HorseSalePost::create(array_merge([
            'en_name' => 'Horse',
            'ar_name' => 'حصان',
            'type_id' => $this->ids['arabian'],
            'gender_id' => $this->ids['mare'],
            'country_id' => $this->ids['oman'],
            'region_id' => $this->ids['muscat'],
            'city_id' => $this->ids['seeb'],
            'price' => 2000,
            'cover_photo' => 'horse-sale/covers/newer.jpg',
            'contact_number' => '99887766',
            'status' => 'active',
        ], $attributes));
    }
}
