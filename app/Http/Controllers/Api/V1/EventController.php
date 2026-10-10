<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EventCategoryResource;
use App\Http\Resources\V1\EventResource;
use App\Models\Event;
use App\Models\EventCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The website's events calendar. The site lists every event; the app pages through a date range,
 * from today on unless asked otherwise.
 */
class EventController extends Controller
{
    use PaginatesListings;

    /**
     * List events
     *
     * Date order. Without `from`, past events are left out.
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            /** `Y-m-d`; defaults to today. */
            'from' => ['nullable', 'date_format:Y-m-d'],
            /** `Y-m-d`. */
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            /** Defaults to 20, at most 50. */
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        return EventResource::collection(
            Event::query()
                ->with('category')
                ->whereDate('date', '>=', $filters['from'] ?? now()->toDateString())
                ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('date', '<=', $to))
                ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
                ->orderBy('date')
                ->orderBy('start_time')
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    /**
     * List event categories
     *
     * @unauthenticated
     */
    public function categories(): AnonymousResourceCollection
    {
        return EventCategoryResource::collection(EventCategory::query()->orderBy('order')->get());
    }
}
