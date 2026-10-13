<?php

namespace Tests\Feature;

use App\Enums\StableApprovalStatus;
use App\Filament\Resources\StableResource;
use App\Filament\Resources\StableResource\Pages\EditStable;
use App\Filament\Resources\StableResource\Pages\ListStables;
use App\Http\Resources\V1\PlaceResource;
use App\Models\Stable;
use App\Models\StableService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** The admin Stables screen: both languages, the rich text description, and its badge. */
class StableAdminResourceTest extends TestCase
{
    use PreparesStableBookings;

    private Stable $stable;

    private StableService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings();
        $this->withoutVite();

        $this->service = StableService::create(['en_name' => 'Riding lessons', 'ar_name' => 'دروس الفروسية']);
        $this->stable = $this->makeStable();
        $this->stable->services()->attach($this->service);

        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_arabic_admin_shows_arabic_labels_names_and_services(): void
    {
        App::setLocale('ar');

        Livewire::test(ListStables::class)
            ->assertOk()
            ->assertSee('فرسان الصحراء')
            ->assertSee('السيب')
            ->assertSee('دروس الفروسية')
            ->assertDontSee('Riding lessons');

        Livewire::test(EditStable::class, ['record' => $this->stable->slug])
            ->assertOk()
            ->assertSee(__('stable_panel.profile.tabs.location'))
            ->assertSee(__('admin_stable.fields.slug'))
            ->assertFormFieldExists('services', fn (Select $field) => $field->getOptionLabels() === [$this->service->id => 'دروس الفروسية'])
            ->assertFormFieldExists('city_id', fn (Select $field) => $field->getOptionLabel() === 'السيب');
    }

    public function test_renaming_a_stable_keeps_its_slug(): void
    {
        $slug = $this->stable->slug;

        Livewire::test(EditStable::class, ['record' => $slug])
            ->fillForm(['en_name' => 'Desert Riders Club', 'en_description' => '<p>Lessons <strong>daily</strong>.</p>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->stable->refresh();
        $this->assertSame('Desert Riders Club', $this->stable->en_name);
        $this->assertSame($slug, $this->stable->slug);
        $this->assertStringContainsString('<strong>daily</strong>', (string) $this->stable->en_description);
    }

    public function test_the_description_is_sanitized_html_and_plain_text_for_the_app(): void
    {
        $this->stable->forceFill([
            'en_description' => '<p>Lessons <strong>daily</strong>.</p><script>alert(1)</script><p><a href="javascript:alert(1)">x</a></p>',
            'ar_description' => "سطر أول\nسطر ثان\n\nفقرة ثانية & أخرى",
        ])->save();

        $html = (string) $this->stable->descriptionHtml();
        $this->assertStringContainsString('<strong>daily</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame("Lessons daily.\nx", $this->stable->descriptionText());

        App::setLocale('ar');
        // An older plain-text description: escaped, its breaks kept.
        $this->assertSame(
            "<p>سطر أول<br>\nسطر ثان</p><p>فقرة ثانية &amp; أخرى</p>",
            Stable::descriptionAsHtml($this->stable->ar_description),
        );

        $json = PlaceResource::make($this->stable)->toArray(Request::create('/'));
        $this->assertSame("سطر أول\nسطر ثان\nفقرة ثانية & أخرى", $json['description']);
    }

    public function test_the_badge_shows_pending_stables_first(): void
    {
        $this->assertSame('1', StableResource::getNavigationBadge());
        $this->assertNull(StableResource::getNavigationBadgeColor());

        $this->makeStable(status: StableApprovalStatus::Pending);
        Once::flush();

        $this->assertSame('1', StableResource::getNavigationBadge());
        $this->assertSame('warning', StableResource::getNavigationBadgeColor());
    }
}
