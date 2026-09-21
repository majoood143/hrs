<section id="race-calendar" aria-label="{{ __('racing.calendar.title') }}">
    @if($unavailable)
        <div class="rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center">
            <p class="font-display text-xl font-semibold text-warm-900">{{ __('racing.unavailable_title') }}</p>
            <p class="mt-2 text-warm-900/60">{{ __('racing.unavailable_body') }}</p>
        </div>
    @else
        <form method="GET" action="{{ $action }}#race-calendar" class="flex flex-wrap items-end justify-center gap-3">
            <div>
                <label for="calendar-season" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('racing.calendar.season') }}</label>
                <select id="calendar-season" name="season" onchange="this.form.submit()"
                        class="w-40 rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    @foreach($seasons as $option)
                        <option value="{{ $option }}" @selected($option === $season)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit" class="btn-warm !px-5 !py-3 text-sm">{{ __('racing.calendar.show') }}</button></noscript>
        </form>

        <p class="mt-4 text-center text-sm text-warm-900/60">
            <span class="me-1 inline-block h-3 w-3 translate-y-0.5 rounded-full bg-warm-600" aria-hidden="true"></span>
            {{ $raceDays > 0 ? __('racing.calendar.legend', ['count' => $raceDays]) : __('racing.calendar.no_meetings') }}
        </p>

        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($months as $month)
                <div class="card-warm !shadow-none p-4">
                    <table class="w-full table-fixed text-center text-sm">
                        <caption class="mb-2 text-start font-display text-lg font-semibold text-warm-900">{{ $month['label'] }}</caption>
                        <thead>
                            <tr class="text-[0.7rem] font-semibold text-warm-700">
                                @foreach(__('racing.calendar.weekdays') as $weekday)
                                    <th scope="col" class="pb-1 font-semibold">{{ $weekday }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($month['weeks'] as $week)
                                <tr>
                                    @foreach($week as $cell)
                                        <td class="p-0.5">
                                            @if($cell && $cell['race'])
                                                <a href="{{ route('racing.calendar.day', $cell['date']) }}"
                                                   @if($cell['meeting']) title="{{ $cell['meeting'] }}" @endif
                                                   aria-label="{{ \Carbon\Carbon::parse($cell['date'])->locale(app()->getLocale())->translatedFormat('j F Y') }}{{ $cell['meeting'] ? ' — ' . $cell['meeting'] : '' }}"
                                                   class="mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-warm-600 font-semibold text-white transition hover:bg-warm-800 focus:outline-none focus:ring-2 focus:ring-warm-400 focus:ring-offset-1">
                                                    {{ $cell['day'] }}
                                                </a>
                                            @elseif($cell)
                                                <span class="flex h-8 w-8 items-center justify-center text-warm-900/50">{{ $cell['day'] }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endif
</section>
