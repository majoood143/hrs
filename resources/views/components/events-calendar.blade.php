@props(['events' => null])

@php
    $events = $events ?? \App\Models\Event::query()->with('category')->orderBy('date')->orderBy('start_time')->get();

    $payload = $events->map(fn (\App\Models\Event $event) => [
        'id' => $event->id,
        'title' => $event->title,
        'description' => $event->description,
        'date' => $event->date->toDateString(),
        'startTime' => \Illuminate\Support\Carbon::parse($event->start_time)->format('H:i'),
        'endTime' => \Illuminate\Support\Carbon::parse($event->end_time)->format('H:i'),
        'startLabel' => \Illuminate\Support\Carbon::parse($event->start_time)->format('g:i A'),
        'endLabel' => \Illuminate\Support\Carbon::parse($event->end_time)->format('g:i A'),
        'category' => $event->category_id,
        'categoryLabel' => $event->categoryLabel(),
        'categoryColor' => $event->categoryColor(),
        'link' => $event->link,
        'isExternal' => $event->isExternalLink(),
        'viewUrl' => route('events.view', $event),
        'viewsLabel' => trans_choice('listings.views', (int) $event->views_count, ['count' => number_format((int) $event->views_count)]),
        'postedLabel' => $event->created_at?->translatedFormat('j F Y'),
    ])->values();

    $categories = \App\Models\EventCategory::query()->orderBy('order')->get()->map(fn ($category) => [
        'key' => $category->id,
        'label' => $category->name,
        'color' => $category->color,
    ])->values();

    $inputClass = 'w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300';
@endphp

<div
    data-events-calendar
    data-locale="{{ app()->getLocale() }}"
    data-csrf="{{ csrf_token() }}"
    {{ $attributes->class(['events-calendar']) }}
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <button type="button" data-events-prev aria-label="{{ __('Previous month') }}"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-warm-200 bg-white text-warm-800 transition hover:bg-warm-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <div data-events-month-label class="w-40 text-center font-display text-lg font-semibold text-warm-900"></div>
            <button type="button" data-events-next aria-label="{{ __('Next month') }}"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-warm-200 bg-white text-warm-800 transition hover:bg-warm-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <button type="button" data-events-today
                class="rounded-full border border-warm-200 bg-white px-3 py-1.5 text-sm font-semibold text-warm-800 transition hover:bg-warm-100">
                {{ __('events.calendar.today') }}
            </button>
        </div>

        <input type="text" data-events-search placeholder="{{ __('events.calendar.search_placeholder') }}"
            class="{{ $inputClass }} sm:w-56">
    </div>

    <div data-events-chips class="mt-3 flex flex-wrap gap-2">
        <button type="button" data-events-chip="all" class="events-chip is-active">
            {{ __('events.calendar.all_categories') }}
        </button>
        @foreach ($categories as $cat)
            <button type="button" data-events-chip="{{ $cat['key'] }}" data-color="{{ $cat['color'] }}" class="events-chip">
                <span class="event-dot" style="background:{{ $cat['color'] }}"></span>
                {{ $cat['label'] }}
            </button>
        @endforeach
    </div>

    <div class="card-warm mt-4 overflow-hidden">
        <div data-events-weekdays class="hidden grid-cols-7 border-b border-warm-200/70 sm:grid"></div>
        <div data-events-grid class="grid grid-cols-7"></div>
    </div>

    <script type="application/json" data-events-payload>{!! json_encode($payload, JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/json" data-events-i18n>{!! json_encode([
        'noEventsDay' => __('events.calendar.no_events_day'),
        'more' => __('events.calendar.more'),
        'noLink' => __('events.calendar.no_link'),
        'posted' => __('listings.posted_on'),
    ], JSON_UNESCAPED_UNICODE) !!}</script>
</div>

<div data-events-drawer-backdrop class="hidden fixed inset-0 z-40 bg-warm-950/40"></div>
<div data-events-drawer class="bg-white shadow-2xl">
    <div class="flex items-center justify-between px-5 pt-4">
        <h3 data-events-drawer-title class="font-display text-base font-semibold text-warm-900"></h3>
        <button type="button" data-events-drawer-close aria-label="{{ __('Close') }}"
            class="inline-flex h-8 w-8 items-center justify-center rounded-full text-warm-700 hover:bg-warm-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    <div data-events-drawer-content class="flex flex-col gap-2 px-5 pb-6 pt-3"></div>
</div>
