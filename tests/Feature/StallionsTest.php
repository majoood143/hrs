<?php

namespace Tests\Feature;

use App\Filament\Resources\StallionResource\Pages\EditStallion;
use App\Filament\Resources\StallionResource\Pages\ListStallions;
use App\Models\Stallion;
use Database\Seeders\StallionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\Concerns\PreparesSiteLayout;
use Tests\TestCase;

/** Breeding stallions: the API, the website pages, the admin screen and the 2025 catalogue seeder. */
class StallionsTest extends TestCase
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
    }

    public function test_the_api_lists_searches_and_shows_stallions_in_the_request_language(): void
    {
        $sahm = $this->stallion(['en_name' => 'Al Sahm', 'ar_name' => 'السهم', 'sire_en' => 'Amer (SA)', 'sire_ar' => 'عامر', 'runs' => 22, 'wins' => 2, 'places' => 9]);
        $arkan = $this->stallion(['en_name' => 'Arkan', 'ar_name' => 'أركان', 'colour' => 'chestnut', 'racing_horse_id' => 4321]);
        $this->stallion(['en_name' => 'Hidden', 'is_active' => false]);

        $this->getJson('/api/v1/stallions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Al Sahm')
            ->assertJsonPath('data.0.record', ['runs' => 22, 'wins' => 2, 'places' => 9, 'rating' => null]);

        $this->assertSame([$sahm->id], $this->getJson('/api/v1/stallions?q='.urlencode('عامر'))->json('data.*.id'));
        $this->assertSame([$arkan->id], $this->getJson('/api/v1/stallions?colour=chestnut')->json('data.*.id'));
        $this->getJson('/api/v1/stallions?colour=pink')->assertUnprocessable();

        $this->getJson("/api/v1/stallions/{$sahm->id}", ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.name', 'السهم')
            ->assertJsonPath('data.colour.label', 'أشهب')
            ->assertJsonPath('data.pedigree.sire', 'عامر')
            ->assertJsonPath('data.pedigree.dam', 'Nisaee (FR)')
            ->assertJsonPath('data.pedigree.sire_sire', 'Wafi (SA)')
            ->assertJsonPath('data.pedigree.dam_dam_dam', null)
            ->assertJsonPath('data.city.name', 'السيب')
            ->assertJsonPath('data.share_url', route('stallions.show', $sahm));

        $this->getJson('/api/v1/stallions/'.($arkan->id + 1))->assertNotFound();
        $this->postJson("/api/v1/views/stallions/{$arkan->id}")->assertOk();
        $this->assertSame(1, $arkan->fresh()->views_count);
    }

    public function test_the_website_shows_the_pedigree_the_record_and_the_racing_history_link(): void
    {
        $sahm = $this->stallion(['en_name' => 'Al Sahm', 'racing_horse_id' => 4321, 'runs' => 22, 'wins' => 2, 'highlights_en' => '1st Oman Derby']);
        $this->stallion(['en_name' => 'Hidden Horse', 'is_active' => false]);

        $this->get(route('stallions.index'))->assertOk()->assertSee('Al Sahm')->assertSee('Amer (SA) × Nisaee (FR)')->assertDontSee('Hidden Horse');
        $this->get(route('stallions.index', ['q' => 'nobody']))->assertOk()->assertSee(__('stallions.no_results_title'));

        $this->get(route('stallions.show', $sahm))
            ->assertOk()
            ->assertSee('Wafi (SA)')
            ->assertSee('1st Oman Derby')
            ->assertSee(route('racing.profile', ['entity' => 'horse', 'id' => 4321]));
        $this->assertSame(1, $sahm->fresh()->views_count);
    }

    public function test_the_seeder_adds_the_catalogue_once_with_photos_and_keeps_admin_edits(): void
    {
        $seeder = new StallionSeeder;
        $seeder->run();

        $this->assertSame(49, Stallion::count());
        $bahar = Stallion::where('en_name', 'Bahar Muscat')->sole();
        $this->assertSame('بحر مسقط', $bahar->ar_name);
        $this->assertSame([16, 5, 5, 111], [$bahar->runs, $bahar->wins, $bahar->places, $bahar->rating]);
        $this->assertSame('Hatteen (AE)', $bahar->pedigree['dam_sire']);
        Storage::disk('public')->assertExists($bahar->cover_photo);
        $this->assertSame($this->ids['seeb'], Stallion::where('en_name', 'Sand Champ')->value('city_id'));
        $this->assertNull(Stallion::where('en_name', 'Thawi')->value('cover_photo'));

        $bahar->update(['contact_number' => '91111111']);
        $seeder->run();

        $this->assertSame(49, Stallion::count());
        $this->assertSame('91111111', $bahar->fresh()->contact_number);
    }

    public function test_the_admin_lists_and_edits_a_stallion_with_its_pedigree(): void
    {
        $sahm = $this->stallion(['en_name' => 'Al Sahm']);
        Gate::before(fn () => true);
        $this->withoutVite();
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListStallions::class)->assertCanSeeTableRecords([$sahm]);

        Livewire::test(EditStallion::class, ['record' => $sahm->getKey()])
            ->assertFormFieldExists('pedigree.dam_dam_dam')
            ->fillForm(['pedigree.dam_dam_dam' => 'Nevadour (FR)', 'racing_horse_id' => 99])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nevadour (FR)', $sahm->fresh()->pedigree['dam_dam_dam']);
        $this->assertSame('Wafi (SA)', $sahm->fresh()->pedigree['sire_sire']);
        $this->assertSame(99, $sahm->fresh()->racing_horse_id);
    }

    /** @param  array<string, mixed>  $values */
    private function stallion(array $values): Stallion
    {
        return Stallion::query()->forceCreate($values + [
            'ar_name' => 'فحل', 'colour' => 'grey', 'foaled_year' => 2012, 'origin' => 'GB',
            'sire_en' => 'Amer (SA)', 'dam_en' => 'Nisaee (FR)', 'pedigree' => ['sire_sire' => 'Wafi (SA)'],
            'city_id' => $this->ids['seeb'], 'contact_number' => '99349099', 'is_active' => true,
        ]);
    }
}
