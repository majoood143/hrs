@php $m = fn (int $baisa) => \App\Support\Money::formatHtml($baisa); @endphp
<x-filament-widgets::widget>
    @if($rows->isNotEmpty() || ! $this->scopeStable())
        <x-filament::section :heading="__('stable_insights.stables.heading')" :description="__('stable_insights.stables.description')" icon="heroicon-o-home-modern">
            @if($rows->isEmpty())
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('stable_insights.services.empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                <th class="pb-2 text-start font-medium">{{ __('stable_statement.fields.stable') }}</th>
                                <th class="pb-2 text-end font-medium">{{ __('stable_insights.services.sold') }}</th>
                                <th class="pb-2 text-end font-medium">{{ __('stable_insights.kpi.sales') }}</th>
                                <th class="pb-2 text-end font-medium">{{ __('stable_insights.chart.stables_share') }}</th>
                                <th class="pb-2 text-end font-medium">{{ __('stable_insights.kpi.commission') }}</th>
                                <th class="pb-2 text-end font-medium">{{ __('stable_insights.kpi.our_share') }}</th>
                                <th class="pb-2"><span class="sr-only">{{ __('stable_statement.admin.statement') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($rows as $row)
                                <tr>
                                    <td class="py-2 pe-3">
                                        <p class="font-medium text-gray-950 dark:text-white">{{ $row['stable']?->en_name ?? '#'.$row['stable_id'] }}</p>
                                        <div class="mt-1 h-1.5 w-full max-w-48 rounded-full bg-gray-100 dark:bg-white/10" aria-hidden="true">
                                            <div class="h-full rounded-full bg-[#2a78d6] dark:bg-[#3987e5]" style="width: {{ max(2, round($row['collected'] * 100 / $max)) }}%"></div>
                                        </div>
                                    </td>
                                    <td class="py-2 pe-3 text-end tabular-nums">{{ $row['count'] }}</td>
                                    <td class="py-2 pe-3 text-end tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $m($row['collected']) }}</td>
                                    <td class="py-2 pe-3 text-end tabular-nums" dir="ltr">{{ $m($row['stable_share']) }}</td>
                                    <td class="py-2 pe-3 text-end tabular-nums" dir="ltr">{{ $m($row['commission']) }}</td>
                                    <td class="py-2 pe-3 text-end font-semibold tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $m($row['our_share']) }}</td>
                                    <td class="py-2 text-end">
                                        <x-filament::link :href="$statementUrl($row['stable_id'])" icon="heroicon-o-document-text" size="sm">{{ __('stable_statement.admin.statement') }}</x-filament::link>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
