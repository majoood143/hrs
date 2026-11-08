<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Models\Event;
use App\Models\EventCategory;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** Admin → Events: an event can run for several days. */
class EventFormTest extends TestCase
{
    use PreparesStableBookings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings();
        $this->withoutVite();

        foreach (['2026_09_13_235959_create_event_categories_table', '2026_09_14_000000_create_events_table', '2026_11_08_000001_add_end_date_to_events_table'] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }

        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function submit(array $values)
    {
        $category = EventCategory::query()->create(['en_name' => 'Racing', 'ar_name' => 'سباقات', 'color' => '#c00000']);

        return Livewire::test(CreateEvent::class)
            ->fillForm($values + [
                'title' => ['en' => 'Show', 'ar' => 'عرض'],
                'date' => '2026-12-01',
                'start_time' => '16:00',
                'end_time' => '18:00',
                'category_id' => $category->id,
            ])
            ->call('create');
    }

    public function test_a_later_end_date_allows_an_earlier_end_time(): void
    {
        $this->submit(['end_date' => '2026-12-03', 'end_time' => '12:00'])->assertHasNoFormErrors();

        $event = Event::query()->sole();
        $this->assertTrue($event->isMultiDay());
        $this->assertSame('2026-12-03', $event->lastDay()->toDateString());
    }

    public function test_the_end_date_cannot_be_before_the_start_date(): void
    {
        $this->submit(['end_date' => '2026-11-30'])->assertHasFormErrors(['end_date']);
    }

    public function test_a_one_day_event_still_needs_its_end_time_after_the_start(): void
    {
        $this->submit(['end_time' => '12:00'])->assertHasFormErrors(['end_time']);
        $this->submit(['end_date' => '2026-12-01', 'end_time' => '12:00'])->assertHasFormErrors(['end_time']);
    }
}
