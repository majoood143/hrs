<?php

namespace Tests\Feature;

use App\Filament\Resources\MobileAnnouncementResource\Pages\EditMobileAnnouncement;
use App\Filament\Resources\MobileAnnouncementResource\Pages\ListMobileAnnouncements;
use App\Filament\Resources\MobileAnnouncementResource\Widgets\AnnouncementOutcomes;
use App\Filament\Resources\MobileAnnouncementResource\Widgets\AnnouncementStatsOverview;
use App\Filament\Resources\MobileAnnouncementResource\Widgets\TopAnnouncements;
use App\Models\AppEvent;
use App\Models\MobileAnnouncement;
use App\Support\MobileApp\AnnouncementInsights;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** Admin → Mobile app → Announcements: the app reports popups shown / tapped / closed, and the widgets show them. */
class AnnouncementInsightsTest extends TestCase
{
    use PreparesStableBookings;

    private const INSTALL = ['X-Install-Id' => 'a1b2c3d4-0000-4000-8000-123456789abc', 'X-App-Platform' => 'ios', 'X-App-Version' => '1.2.0'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->prepareStableBookings();
        (require database_path('migrations/2026_10_13_120000_create_mobile_announcements_table.php'))->up();
    }

    private function announcement(string $title): MobileAnnouncement
    {
        return MobileAnnouncement::query()->create(['en_title' => $title, 'ar_title' => $title, 'action_type' => 'url', 'action_url' => 'https://example.com', 'frequency' => 'once', 'is_active' => true]);
    }

    public function test_the_apps_popup_events_add_up_per_announcement(): void
    {
        $sale = $this->announcement('Sale');
        $other = $this->announcement('Other');

        $this->postJson('/api/v1/events', ['events' => [
            ['name' => 'announcement_shown', 'properties' => ['id' => (string) $sale->id]],
            ['name' => 'announcement_clicked', 'properties' => ['id' => (string) $sale->id]],
            ['name' => 'announcement_shown', 'properties' => ['id' => (string) $other->id]],
            ['name' => 'announcement_dismissed', 'properties' => ['id' => (string) $other->id]],
        ]], self::INSTALL)->assertStatus(202)->assertJsonPath('accepted', 4);
        $this->postJson('/api/v1/events', ['events' => [['name' => 'announcement_shown', 'properties' => ['id' => (string) $sale->id]]]], ['X-Install-Id' => 'another-install-1'])->assertStatus(202);
        AppEvent::query()->create(['install_id' => 'old-install-1', 'name' => 'announcement_shown', 'properties' => ['id' => (string) $sale->id], 'occurred_at' => now()->subDays(40)]);

        $all = AnnouncementInsights::summary();
        $this->assertSame([3, 1, 1, 1, 2, 2], [$all['shown'], $all['shown_before'], $all['clicked'], $all['dismissed'], $all['reach'], $all['live']]);
        $one = AnnouncementInsights::summary($sale->id);
        $this->assertSame([2, 1, 0], [$one['shown'], $one['clicked'], $one['dismissed']]);
        $this->assertSame(2, array_sum(AnnouncementInsights::daily($sale->id)['shown']));
        $this->assertSame(['shown' => 3, 'clicked' => 1, 'dismissed' => 0], AnnouncementInsights::totals()[$sale->id]);
    }

    public function test_the_list_and_edit_pages_show_the_widgets_to_admins_only(): void
    {
        $sale = $this->announcement('Sale');
        foreach (['announcement_shown', 'announcement_shown', 'announcement_clicked'] as $name) {
            AppEvent::query()->create(['install_id' => 'install-'.$name, 'name' => $name, 'properties' => ['id' => (string) $sale->id], 'occurred_at' => now()]);
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->assertFalse(AnnouncementStatsOverview::canView());

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });

        Livewire::test(ListMobileAnnouncements::class)->assertSuccessful()->assertSeeLivewire(AnnouncementStatsOverview::class)
            ->assertTableColumnStateSet('shown', 2, $sale)->assertTableColumnStateSet('clicked', 1, $sale);
        Livewire::test(AnnouncementStatsOverview::class)->assertSuccessful()->assertSee('50%');
        Livewire::test(AnnouncementOutcomes::class)->assertSuccessful()->assertSee(__('admin_widgets.announcements.no_action'));
        Livewire::test(TopAnnouncements::class)->assertSuccessful()->assertSee('Sale');
        Livewire::test(EditMobileAnnouncement::class, ['record' => $sale->id])->assertSuccessful()->assertSeeLivewire(AnnouncementStatsOverview::class);
    }
}
