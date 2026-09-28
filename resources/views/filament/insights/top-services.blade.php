@php $m = fn (int $baisa) => \App\Support\Money::formatHtml($baisa); @endphp
<x-filament-widgets::widget>
    <x-filament::section :heading="__('stable_insights.services.heading')" icon="heroicon-o-trophy">
        @if($services->isEmpty())
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('stable_insights.services.empty') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="pb-2 text-start font-medium">{{ __('stable_insights.services.name') }}</th>
                        <th class="pb-2 text-end font-medium">{{ __('stable_insights.services.sold') }}</th>
                        <th class="pb-2 text-end font-medium">{{ __('stable_insights.kpi.sales') }}</th>
                        <th class="pb-2 text-end font-medium">{{ $owner ? __('stable_insights.kpi.stable_share') : __('stable_insights.kpi.commission') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($services as $service)
                        <tr>
                            <td class="py-2 pe-3">
                                <p class="font-medium text-gray-950 dark:text-white">{{ $service['name'] }}</p>
                                {{-- the bar's length is its sales against the best seller --}}
                                <div class="mt-1 h-1.5 w-full max-w-48 rounded-full bg-gray-100 dark:bg-white/10" aria-hidden="true">
                                    <div class="h-full rounded-full bg-[#2a78d6] dark:bg-[#3987e5]" style="width: {{ max(2, round($service['collected'] * 100 / $max)) }}%"></div>
                                </div>
                            </td>
                            <td class="py-2 pe-3 text-end tabular-nums text-gray-700 dark:text-gray-300">
                                {{ $service['count'] }}@if($service['riders'] > 0)<span class="block text-xs text-gray-500">{{ trans_choice('stable_bookings.riders_count', $service['riders'], ['count' => $service['riders']]) }}</span>@endif
                            </td>
                            <td class="py-2 pe-3 text-end tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $m($service['collected']) }}</td>
                            <td class="py-2 text-end tabular-nums text-gray-700 dark:text-gray-300" dir="ltr">{{ $m($owner ? $service['stable_share'] : $service['commission']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
