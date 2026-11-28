@php $m = fn (int $baisa) => \App\Support\Money::formatHtml($baisa); @endphp
<x-filament-widgets::widget>
    <x-filament::section :heading="__('stable_extras.insights.title')" :description="__('stable_extras.insights.description')" icon="heroicon-o-sparkles">
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('stable_extras.insights.revenue') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $m($extras['revenue']) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('stable_extras.insights.attach_rate') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $extras['rate'] === null ? '—' : $extras['rate'].'%' }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400"><span dir="ltr">{{ $extras['with_extras'] }} / {{ $extras['bookings'] }}</span></p>
            </div>
        </div>

        @if($extras['top']->isEmpty())
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('stable_extras.insights.none') }}</p>
        @else
            <p class="mt-4 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('stable_extras.insights.top') }}</p>
            <table class="mt-1 w-full text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($extras['top'] as $extra)
                        <tr>
                            <td class="py-2 pe-3">
                                <p class="font-medium text-gray-950 dark:text-white">{{ $extra['name'] }}</p>
                                {{-- the bar's length is its revenue against the best seller --}}
                                <div class="mt-1 h-1.5 w-full max-w-48 rounded-full bg-gray-100 dark:bg-white/10" aria-hidden="true">
                                    <div class="h-full rounded-full bg-[#2a78d6] dark:bg-[#3987e5]" style="width: {{ max(2, round($extra['amount'] * 100 / $max)) }}%"></div>
                                </div>
                            </td>
                            <td class="py-2 pe-3 text-end tabular-nums text-gray-700 dark:text-gray-300">× {{ $extra['quantity'] }}</td>
                            <td class="py-2 text-end tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $m($extra['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($extras['stables']->isNotEmpty())
                <p class="mt-4 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('stable_extras.insights.stable') }}</p>
                <ul class="mt-1 divide-y divide-gray-100 text-sm dark:divide-white/5">
                    @foreach($extras['stables'] as $row)
                        <li class="flex justify-between py-2"><span class="text-gray-950 dark:text-white">{{ $row['name'] }}</span><span class="tabular-nums" dir="ltr">{{ $m($row['amount']) }}</span></li>
                    @endforeach
                </ul>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
