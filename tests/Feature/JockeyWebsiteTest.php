<?php

namespace Tests\Feature;

use App\Events\PublicPostSubmitted;
use App\Models\Jockey;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\Concerns\PreparesSiteLayout;
use Tests\TestCase;

/** The website's jockeys board, a jockey's page and the "add yourself" form. */
class JockeyWebsiteTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;
    use PreparesSiteLayout;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSiteLayout();
        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        $this->ids = $this->seedLocations();

        Storage::fake('public');
        Event::fake([PublicPostSubmitted::class]);
    }

    public function test_the_board_filters_by_type_and_hides_pending_jockeys(): void
    {
        $this->jockey(['en_name' => 'Hamed Flat', 'types' => ['flat_racing']]);
        $this->jockey(['en_name' => 'Said Endurance', 'types' => ['endurance']]);
        $this->jockey(['en_name' => 'Waiting Rider', 'status' => 'pending']);

        $this->get(route('jockeys.index'))
            ->assertOk()
            ->assertSee('Hamed Flat')
            ->assertSee('Said Endurance')
            ->assertDontSee('Waiting Rider');

        $this->get(route('jockeys.index', ['type' => 'endurance']))
            ->assertOk()
            ->assertSee('Said Endurance')
            ->assertDontSee('Hamed Flat');
    }

    public function test_a_jockey_page_links_the_racing_record_only_once_checked(): void
    {
        $checked = $this->jockey(['en_name' => 'Hamed', 'racing_jockey_id' => 77, 'racing_verified' => true]);
        $unchecked = $this->jockey(['en_name' => 'Said', 'racing_jockey_id' => 88]);

        $this->get(route('jockeys.show', $checked))
            ->assertOk()
            ->assertSee('Hamed')
            ->assertSee(route('racing.profile', ['entity' => 'jockey', 'id' => 77]));

        $this->get(route('jockeys.show', $unchecked))
            ->assertOk()
            ->assertDontSee(route('racing.profile', ['entity' => 'jockey', 'id' => 88]));

        $this->assertSame(1, $checked->fresh()->views_count);
        $this->get(route('jockeys.show', $this->jockey(['status' => 'pending'])))->assertNotFound();
    }

    public function test_the_form_needs_the_captcha_and_leaves_the_racing_link_to_an_admin(): void
    {
        $captcha = str_repeat('a', config('filament-captcha.length', 5));
        $fields = [
            'en_name' => 'Hamed', 'ar_name' => 'حامد', 'types' => ['flat_racing', 'dressage'], 'city_id' => $this->ids['seeb'],
            'contact_number' => '91234567', 'racing_jockey_id' => 77,
        ];

        $this->get(route('jockeys.create'))
            ->assertOk()
            ->assertSee(__('jockeys.post_page_title'))
            ->assertSee(__('jockeys.select_country'))
            ->assertSee(__('jockeys.location_hint'))
            ->assertSee('data-location-tree', false)
            ->assertSee('"name":"Seeb"', false);

        $this->withSession(['filament_captcha_code' => $captcha])
            ->post(route('jockeys.store'), $fields + ['captcha' => 'wrong'])
            ->assertSessionHasErrors('captcha');

        $this->withSession(['filament_captcha_code' => $captcha])
            ->post(route('jockeys.store'), $fields + ['captcha' => $captcha])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('jockeys.index'));

        $jockey = Jockey::sole();
        $this->assertSame(['flat_racing', 'dressage'], $jockey->types);
        $this->assertSame('91234567', $jockey->contact_number);
        $this->assertNull($jockey->racing_jockey_id);
        $this->assertNull($jockey->cover_photo);
    }

    /** @param  array<string, mixed>  $values */
    private function jockey(array $values): Jockey
    {
        return Jockey::query()->forceCreate($values + [
            'en_name' => 'Rider', 'ar_name' => 'فارس', 'types' => ['other'], 'country_id' => $this->ids['oman'],
            'region_id' => $this->ids['muscat'], 'city_id' => $this->ids['seeb'], 'contact_number' => '99887766', 'status' => 'active',
        ]);
    }
}
