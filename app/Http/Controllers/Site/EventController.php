<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SiteSetting;
use App\Support\Seo;
use App\Support\ViewCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        return view('site.events.index', Seo::forSlug(
            'events',
            __('events.calendar.title').' — '.SiteSetting::siteName(),
        ));
    }

    /**
     * Events have no page of their own: the calendar posts here when its drawer shows one.
     */
    public function recordView(Request $request, Event $event): JsonResponse
    {
        ViewCounter::record($event, $request);

        return response()->json([
            'views' => $event->views_count,
            'label' => trans_choice('listings.views', $event->views_count, ['count' => number_format($event->views_count)]),
        ]);
    }
}
