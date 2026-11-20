<?php

namespace Tests\Feature;

use App\Filament\Resources\ClinicResource;
use App\Filament\Resources\ClinicResource\Pages\ListClinics;
use App\Filament\Resources\ClinicResource\Pages\ViewClinic;
use App\Filament\Support\ListingViews\AppViewsChart;
use App\Filament\Support\ListingViews\ListingViewsStats;
use App\Filament\Support\ListingViews\RecordViewsStats;
use App\Filament\Support\ListingViews\TopViewedListings;
use App\Models\Clinic;
use App\Support\ListingViews;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

/** The listings' views in the admin: the board's figures, one listing's rank, the app's views per day. */
class ListingViewsTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        (require database_path('migrations/2026_11_14_000002_create_app_events_table.php'))->up();
        Schema::disableForeignKeyConstraints(); // app_events.customer_id points at a table this test does not need
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function seedClinics(): array
    {
        $top = $this->row('clinics', ['en_name' => 'Busy', 'slug' => 'busy', 'views_count' => 30]);
        $mid = $this->row('clinics', ['en_name' => 'Quiet', 'slug' => 'quiet', 'views_count' => 10]);
        $this->row('clinics', ['en_name' => 'New', 'slug' => 'new', 'views_count' => 0]);
        foreach ([now()->subDay(), now()->subDays(2), now()->subDays(40)] as $at) {
            DB::table('app_events')->insert(['name' => 'listing_viewed', 'properties' => json_encode(['section' => 'clinics', 'id' => 'quiet']), 'occurred_at' => $at, 'created_at' => $at]);
        }
        DB::table('app_events')->insert(['name' => 'listing_viewed', 'properties' => json_encode(['section' => 'shops', 'id' => 'quiet']), 'occurred_at' => now(), 'created_at' => now()]);

        return [$top, $mid];
    }

    public function test_figures_per_board_and_listing(): void
    {
        [$top, $mid] = $this->seedClinics();

        $summary = ListingViews::summary(Clinic::class);
        $this->assertSame([40, 3, 1], [$summary['total'], $summary['listings'], $summary['unseen']]);
        $this->assertSame([['id' => $top, 'views' => 30], ['id' => $mid, 'views' => 10]], $summary['top']);
        $this->assertSame(['rank' => 2, 'of' => 3, 'share' => 25.0], ListingViews::rank(Clinic::find($mid)));

        $board = ListingViews::appDaily(Clinic::class);
        $this->assertSame([2, 1], [$board['total'], $board['before']]);
        $this->assertCount(ListingViews::DAYS, $board['views']);
        $this->assertSame(0, ListingViews::appDaily(Clinic::class, Clinic::find($top))['total']);
    }

    public function test_the_pages_show_the_widgets_to_admins_only(): void
    {
        [, $mid] = $this->seedClinics();

        $this->actingAs(new class(['name' => 'Nobody']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Livewire::test(ListingViewsStats::class, ['resource' => ClinicResource::class])->assertForbidden();

        Gate::before(fn () => true);
        Livewire::test(ListClinics::class)->assertSuccessful()->assertSeeLivewire(ListingViewsStats::class)->assertSeeLivewire(TopViewedListings::class);
        Livewire::test(ListingViewsStats::class, ['resource' => ClinicResource::class])->assertSuccessful()->assertSee('40')->assertSee('13.3');
        Livewire::test(TopViewedListings::class, ['resource' => ClinicResource::class])->assertSuccessful()->assertSee('Busy')->assertSee('Quiet');
        Livewire::test(AppViewsChart::class, ['resource' => ClinicResource::class])->assertSuccessful();

        Livewire::test(ViewClinic::class, ['record' => 'quiet'])->assertSuccessful()->assertSeeLivewire(RecordViewsStats::class);
        Livewire::test(RecordViewsStats::class, ['resource' => ClinicResource::class, 'record' => Clinic::find($mid)])
            ->assertSuccessful()->assertSee('#2')->assertSee('25%');
    }
}
