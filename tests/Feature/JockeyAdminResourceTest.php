<?php

namespace Tests\Feature;

use App\Filament\Resources\JockeyResource\Pages\EditJockey;
use App\Filament\Resources\JockeyResource\Pages\ListJockeys;
use App\Models\Jockey;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

/** The admin Jockeys screen: Arabic labels, the riding types and the racing check. */
class JockeyAdminResourceTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    private Jockey $jockey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        $this->withoutVite();
        Storage::fake('public');
        $ids = $this->seedLocations();

        $this->jockey = Jockey::query()->forceCreate([
            'en_name' => 'Hamed Rider', 'ar_name' => 'حامد الفارس', 'types' => ['flat_racing'],
            'country_id' => $ids['oman'], 'region_id' => $ids['muscat'], 'city_id' => $ids['seeb'],
            'status' => 'pending', 'contact_number' => '+96891234567', 'racing_jockey_id' => 77,
        ]);

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_arabic_admin_lists_names_types_and_statuses(): void
    {
        App::setLocale('ar');

        Livewire::test(ListJockeys::class)
            ->assertOk()
            ->assertSee('حامد الفارس')
            ->assertSee('سباقات السرعة')
            ->assertSee('بانتظار الموافقة')
            ->assertDontSee('Hamed Rider');
    }

    public function test_types_and_the_racing_check_are_saved(): void
    {
        Livewire::test(EditJockey::class, ['record' => $this->jockey->getKey()])
            ->assertOk()
            ->fillForm(['types' => ['flat_racing', 'endurance'], 'racing_verified' => true, 'status' => 'active'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->jockey->refresh();
        $this->assertSame(['flat_racing', 'endurance'], $this->jockey->types);
        $this->assertSame(77, $this->jockey->verified_racing_id);
    }
}
