<?php

namespace Tests\Feature;

use App\Filament\Resources\CityResource\Pages\EditCity;
use App\Filament\Resources\CityResource\Pages\ListCities;
use App\Filament\Resources\CountryResource\Pages\EditCountry;
use App\Filament\Resources\CountryResource\Pages\ListCountries;
use App\Filament\Resources\CountryResource\RelationManagers\RegionRelationManager;
use App\Filament\Resources\RegionResource\Pages\EditRegion;
use App\Filament\Resources\RegionResource\Pages\ListRegions;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

/** Countries, regions and cities in the admin: both languages, and never deleted while in use. */
class LocationAdminResourcesTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        $this->withoutVite();
        $this->ids = $this->seedLocations();

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_arabic_admin_shows_arabic_labels_and_location_names(): void
    {
        App::setLocale('ar');

        Livewire::test(ListCountries::class)->assertOk()->assertSee(__('admin_locations.fields.regions'))->assertSee(__('admin_country.fields.public'));
        Livewire::test(ListRegions::class)->assertOk()->assertSee('مسقط')->assertSee('عمان')->assertSee(__('admin_locations.fields.cities'));
        Livewire::test(ListCities::class)->assertOk()->assertSee('السيب')->assertSee('مسقط')->assertSee(__('admin_locations.fields.region'));

        Livewire::test(EditRegion::class, ['record' => $this->ids['muscat']])
            ->assertOk()
            ->assertFormFieldExists('country_id', fn (Select $field) => $field->getOptionLabel() === 'عمان');

        Livewire::test(EditCity::class, ['record' => $this->ids['seeb']])
            ->assertOk()
            ->assertFormFieldExists('country_id', fn (Select $field) => $field->getOptionLabel() === 'عمان')
            ->assertFormFieldExists('region_id', fn (Select $field) => $field->getOptionLabel() === 'مسقط');
    }

    public function test_a_location_in_use_is_never_deleted(): void
    {
        $this->row('stables', ['en_name' => 'Desert Riders', 'slug' => 'desert-riders', 'country_id' => $this->ids['oman'], 'region_id' => $this->ids['muscat'], 'city_id' => $this->ids['seeb']]);

        // Oman has regions, Muscat has cities, Seeb has a stable: all three refuse.
        Livewire::test(ListCountries::class)->callTableAction(DeleteAction::class, $this->ids['oman'])->assertNotified(__('admin_locations.in_use.title'));
        Livewire::test(ListRegions::class)->callTableAction(DeleteAction::class, $this->ids['muscat']);
        Livewire::test(ListCities::class)->callTableBulkAction(DeleteBulkAction::class, [$this->ids['seeb'], $this->ids['bawshar']]);

        $this->assertNotNull(Country::find($this->ids['oman']));
        $this->assertNotNull(Region::find($this->ids['muscat']));
        $this->assertNotNull(City::find($this->ids['seeb']));
        $this->assertNull(City::find($this->ids['bawshar']), 'An unused city in the same selection is deleted.');
        $this->assertSame(1, DB::table('stables')->count());

        // A transfer post holds a city too, at either end.
        $tiny = City::query()->forceCreate(['region_id' => $this->ids['muscat'], 'en_name' => 'Amerat', 'ar_name' => 'العامرات']);
        $this->row('transfer_posts', ['type' => 'request', 'transfer_date' => now()->addDay()->toDateString(), 'to_city_id' => $tiny->id]);
        $this->assertFalse($tiny->delete());

        // Nothing points at the hidden country: it goes.
        Livewire::test(ListCountries::class)->callTableAction(DeleteAction::class, $this->ids['hidden']);
        $this->assertNull(Country::find($this->ids['hidden']));
    }

    public function test_a_region_added_from_its_country_needs_both_names(): void
    {
        Livewire::test(RegionRelationManager::class, ['ownerRecord' => Country::find($this->ids['oman']), 'pageClass' => EditCountry::class])
            ->callTableAction('create', data: ['en_name' => 'Batinah'])
            ->assertHasTableActionErrors(['ar_name' => 'required']);

        Livewire::test(RegionRelationManager::class, ['ownerRecord' => Country::find($this->ids['oman']), 'pageClass' => EditCountry::class])
            ->callTableAction('create', data: ['en_name' => 'Batinah', 'ar_name' => 'الباطنة'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('الباطنة', Region::query()->where('en_name', 'Batinah')->value('ar_name'));
    }
}
