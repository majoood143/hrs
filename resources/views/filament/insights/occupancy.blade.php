@php
    // one hue, light → dark for "fuller" on light surfaces; the same ramp reversed on dark ones
    $steps = [
        ['bg-[#cde2fb] text-gray-900 dark:bg-[#0d366b] dark:text-white'],
        ['bg-[#9ec5f4] text-gray-900 dark:bg-[#184f95] dark:text-white'],
        ['bg-[#6da7ec] text-gray-900 dark:bg-[#256abf] dark:text-white'],
        ['bg-[#3987e5] text-white dark:bg-[#3987e5] dark:text-white'],
        ['bg-[#256abf] text-white dark:bg-[#6da7ec] dark:text-gray-900'],
        ['bg-[#184f95] text-white dark:bg-[#9ec5f4] dark:text-gray-900'],
        ['bg-[#0d366b] text-white dark:bg-[#cde2fb] dark:text-gray-900'],
    ];
    $step = fn (int $pct) => $steps[min(6, intdiv($pct, 15))][0];
    $days = collect($occupancy['days'])->mapWithKeys(fn (int $d) => [$d => __('stable_panel.weekdays_short.'.$d)]);
    $total = $occupancy['capacity'] > 0 ? (int) round($occupancy['booked'] * 100 / $occupancy['capacity']) : null;
@endphp

<x-filament-widgets::widget>
    <x-filament::section :heading="__('stable_insights.occupancy.heading')" :description="__('stable_insights.occupancy.description')" icon="heroicon-o-squares-2x2">
        @if($occupancy['hours'] === [])
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('stable_insights.occupancy.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-separate border-spacing-0.5 text-xs" aria-describedby="occupancy-legend">
                    <thead>
                        <tr>
                            <th class="w-12"><span class="sr-only">{{ __('stable_insights.occupancy.day') }}</span></th>
                            @foreach($occupancy['hours'] as $hour)
                                <th scope="col" class="px-1 pb-1 text-center font-medium text-gray-500 dark:text-gray-400" dir="ltr">{{ sprintf('%02d:00', $hour) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($days as $day => $dayLabel)
                            <tr>
                                <th scope="row" class="pe-2 text-start font-medium text-gray-600 dark:text-gray-300">{{ $dayLabel }}</th>
                                @foreach($occupancy['hours'] as $hour)
                                    @php $cell = $occupancy['cells'][$day][$hour] ?? null; @endphp
                                    @if($cell && $cell['capacity'] > 0)
                                        @php $pct = (int) round($cell['booked'] * 100 / $cell['capacity']); @endphp
                                        <td class="h-9 min-w-11 rounded-md text-center font-semibold tabular-nums {{ $step($pct) }}"
                                            title="{{ __('stable_insights.occupancy.cell', ['day' => $dayLabel, 'time' => sprintf('%02d:00', $hour), 'booked' => $cell['booked'], 'capacity' => $cell['capacity'], 'pct' => $pct]) }}">
                                            {{ $pct }}%
                                        </td>
                                    @else
                                        <td class="h-9 min-w-11 rounded-md bg-gray-50 text-center text-gray-400 dark:bg-white/5 dark:text-gray-500">—</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div id="occupancy-legend" class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span>{{ __('stable_insights.occupancy.overall', ['pct' => $total ?? '—', 'booked' => $occupancy['booked'], 'capacity' => $occupancy['capacity']]) }}</span>
                <span class="flex items-center gap-1" aria-hidden="true">
                    0%
                    @foreach($steps as $s)
                        <span class="inline-block h-3 w-5 rounded-sm {{ $s[0] }}"></span>
                    @endforeach
                    100%
                </span>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
