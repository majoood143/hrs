@php
    // most useful first: what happened, then what is running, then the media
    $buttons = ['results' => 'trophy', 'card' => 'card', 'entries' => 'entries', 'video' => 'play', 'photo' => 'camera'];
@endphp

<section id="race-widget" data-race-widget data-endpoint="{{ route('racing.widget') }}" aria-label="{{ __('racing.widget.title') }}"
         class="transition-opacity duration-150 aria-busy:opacity-60">
    <x-racing.race-widget-icons />

    @if($unavailable)
        <div class="rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center">
            <p class="font-display text-xl font-semibold text-warm-900">{{ __('racing.unavailable_title') }}</p>
            <p class="mt-2 text-warm-900/60">{{ __('racing.unavailable_body') }}</p>
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-[20rem_1fr] lg:items-start lg:gap-6">
            {{-- month picker --}}
            <div class="card-warm !shadow-none p-3 sm:p-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    @if($prevMonth)
                        <a href="{{ $url($prevMonth, $date) }}" data-rw="prev" aria-label="{{ __('racing.widget.prev_month') }}"
                           class="flex h-11 w-11 items-center justify-center rounded-full text-warm-800 transition hover:bg-warm-100 active:bg-warm-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-warm-400">
                            <svg class="h-5 w-5 rtl:rotate-180" aria-hidden="true"><use href="#rw-prev"/></svg>
                        </a>
                    @else
                        <span class="h-11 w-11" aria-hidden="true"></span>
                    @endif

                    <p class="font-display text-lg font-semibold text-warm-900 focus:outline-none" tabindex="-1" data-rw-month aria-live="polite">{{ $monthLabel }}</p>

                    @if($nextMonth)
                        <a href="{{ $url($nextMonth, $date) }}" data-rw="next" aria-label="{{ __('racing.widget.next_month') }}"
                           class="flex h-11 w-11 items-center justify-center rounded-full text-warm-800 transition hover:bg-warm-100 active:bg-warm-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-warm-400">
                            <svg class="h-5 w-5 rtl:rotate-180" aria-hidden="true"><use href="#rw-next"/></svg>
                        </a>
                    @else
                        <span class="h-11 w-11" aria-hidden="true"></span>
                    @endif
                </div>

                <table class="w-full table-fixed text-center text-sm">
                    <thead>
                        <tr class="text-[0.7rem] font-semibold text-warm-700">
                            @foreach(__('racing.calendar.weekdays') as $weekday)
                                <th scope="col" class="pb-1 font-semibold">{{ $weekday }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($weeks as $week)
                            <tr>
                                @foreach($week as $cell)
                                    <td class="p-px">
                                        @if($cell && $cell['race'])
                                            <a href="{{ $url($month, $cell['date']) }}"
                                               @if($cell['selected']) aria-current="date" @endif
                                               data-rw="day"
                                               @if($cell['meeting']) title="{{ $cell['meeting'] }}" @endif
                                               aria-label="{{ $dateLabel($cell['date']) }}{{ $cell['meeting'] ? ' — ' . $cell['meeting'] : '' }}"
                                               class="mx-auto flex h-10 w-10 items-center justify-center rounded-full font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-warm-400 focus-visible:ring-offset-1
                                                      {{ $cell['selected'] ? 'bg-warm-900 text-white ring-2 ring-warm-900 ring-offset-1' : 'bg-warm-600 text-white hover:bg-warm-800 active:bg-warm-900' }}">
                                                {{ $cell['day'] }}
                                            </a>
                                        @elseif($cell)
                                            <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full {{ $cell['today'] ? 'border border-warm-400 font-semibold text-warm-900' : 'text-warm-900/50' }}">{{ $cell['day'] }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <p class="mt-2 text-center text-xs text-warm-900/60">
                    <span class="me-1 inline-block h-2.5 w-2.5 translate-y-px rounded-full bg-warm-600" aria-hidden="true"></span>
                    {{ $raceDays > 0 ? __('racing.widget.legend', ['count' => $raceDays]) : __('racing.widget.no_meetings_month') }}
                </p>
            </div>

            {{-- selected day --}}
            <div class="min-w-0 scroll-mt-4" data-rw-panel>
                @if($date === null)
                    <div class="rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center text-warm-900/70">
                        {{ __('racing.widget.choose') }}
                    </div>
                @else
                    <div class="card-warm !shadow-none overflow-hidden">
                        <div class="border-b border-warm-200 bg-warm-50 px-4 py-3 sm:px-5 sm:py-4">
                            <h3 class="font-display text-lg font-semibold text-warm-900 focus:outline-none sm:text-xl" tabindex="-1" data-rw-focus>{{ $dateLabel($date) }}</h3>

                            @if(! $racesUnavailable)
                                <dl class="mt-1.5 flex flex-wrap gap-x-5 gap-y-0.5 text-sm text-warm-900/70">
                                    <div class="flex gap-1.5"><dt>{{ __('racing.widget.race_count') }}</dt><dd class="font-semibold text-warm-900">{{ count($races) }}</dd></div>
                                    @if($meeting)
                                        <div class="flex gap-1.5"><dt>{{ __('racing.widget.meeting') }}</dt><dd class="font-semibold text-warm-900">{{ $meeting }}</dd></div>
                                    @endif
                                    @if($meetingTime)
                                        <div class="flex gap-1.5"><dt>{{ __('racing.widget.first_race') }}</dt><dd class="font-semibold text-warm-900" dir="ltr">{{ $meetingTime }}</dd></div>
                                    @endif
                                </dl>
                            @endif
                        </div>

                        @if($racesUnavailable)
                            <div class="px-5 py-10 text-center">
                                <p class="font-semibold text-warm-900">{{ __('racing.unavailable_title') }}</p>
                                <p class="mt-1 text-warm-900/70">{{ __('racing.unavailable_body') }}</p>
                            </div>
                        @elseif($races === [])
                            <p class="px-5 py-10 text-center text-warm-900/70">{{ __('racing.widget.no_races') }}</p>
                        @else
                            <ol class="divide-y divide-warm-100">
                                @foreach($races as $race)
                                    @php
                                        $links = array_filter([
                                            'results' => $race['results'] ? route('racing.meeting', ['page' => 'results', 'race' => $race['id']]) : null,
                                            'card' => $race['card'] ? route('racing.meeting', ['page' => 'card', 'race' => $race['id']]) : null,
                                            'entries' => $race['entries'] ? route('racing.meeting', ['page' => 'entries', 'race' => $race['id']]) : null,
                                            'video' => $race['video'],
                                            'photo' => $race['photo'],
                                        ]);
                                    @endphp
                                    <li class="px-4 py-3.5 sm:px-5">
                                        <div class="flex items-start gap-3">
                                            <span class="mt-0.5 inline-flex w-14 shrink-0 justify-center rounded-lg bg-warm-100 py-1 text-sm font-semibold tabular-nums text-warm-800" dir="ltr">{{ $race['time'] ?? '—' }}</span>

                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('racing.race', $race['id']) }}" class="block py-0.5 font-semibold leading-snug text-warm-900 hover:text-warm-700 hover:underline">{{ $race['name'] !== '' ? $race['name'] : __('racing.race_n', ['n' => $race['id']]) }}</a>
                                                @if($race['distance'])
                                                    <p class="text-xs text-warm-900/60">{{ __('racing.widget.distance', ['m' => number_format($race['distance'])]) }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        @if($links !== [])
                                            <div class="mt-3 flex flex-wrap gap-2 sm:ps-17">
                                                @foreach(array_intersect_key($buttons, $links) as $key => $icon)
                                                    <a href="{{ $links[$key] }}" @if(in_array($key, ['video', 'photo'], true)) target="_blank" rel="noopener noreferrer" @endif
                                                       class="inline-flex min-h-10 items-center gap-1.5 rounded-full border px-3.5 text-sm font-semibold transition active:scale-95
                                                              {{ $key === 'results' ? 'border-warm-600 bg-warm-600 text-white hover:bg-warm-800' : 'border-warm-200 bg-white text-warm-800 hover:border-warm-500 hover:text-warm-900 active:bg-warm-100' }}">
                                                        <svg class="h-4 w-4 shrink-0" aria-hidden="true"><use href="#rw-{{ $icon }}"/></svg>
                                                        {{ __('racing.widget.links.' . $key) }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>

                            @if(! collect($races)->contains(fn ($r) => $r['entries'] || $r['card'] || $r['results']))
                                <p class="border-t border-warm-100 bg-warm-50 px-4 py-3 text-xs text-warm-900/60 sm:px-5">{{ __('racing.widget.not_yet') }}</p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</section>
