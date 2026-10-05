{{--
    One form chart (App\Services\Forms\FormChart spec), drawn by resources/js/form-charts.js, with its
    table twin underneath: the numbers stay readable with no JavaScript, for screen readers and in print.
    Shared by the admin Insights page and the public "Form results chart" block.
--}}
@props(['spec', 'id', 'table' => true, 'tableOpen' => false])
@php
    $number = fn (int|float $n): string => rtrim(rtrim(number_format($n, 1, '.', ','), '0'), '.');
    $key = 'form-chart-'.$id.'-'.substr(md5(json_encode($spec)), 0, 12);
    $top = collect($spec['counts'])->sortDesc()->keys()->first();
    $summary = $spec['answered'] > 0 && $top !== null
        ? __('form_charts.summary', ['title' => $spec['title'], 'answer' => $spec['labels'][$top], 'count' => $spec['counts'][$top], 'total' => $spec['answered']])
        : $spec['title'];
    $ticked = $spec['counts'][0] ?? 0;
    $share = $spec['shares'][0] ?? 0;
@endphp

<div {{ $attributes->class(['form-chart']) }} wire:key="{{ $key }}">
    @if($spec['type'] === 'meter')
        <div class="flex flex-col gap-2">
            <p class="text-3xl font-semibold text-gray-950 dark:text-white">{{ $number($share) }}%</p>
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-[#e1e0d9] dark:bg-[#2c2c2a]" role="img" aria-label="{{ $summary }}">
                <div class="h-full rounded-full bg-[#2a78d6] dark:bg-[#3987e5]" style="width: {{ min(100, max(0, $share)) }}%"></div>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('form_charts.ticked_of', ['count' => $ticked, 'total' => $spec['answered']]) }}</p>
        </div>
    @else
        <div wire:ignore data-form-chart class="relative w-full" style="height: {{ (int) $spec['height'] }}px">
            <canvas role="img" aria-label="{{ $summary }}"></canvas>
            <script type="application/json">{!! json_encode($spec, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
        </div>
    @endif

    @if($table && $spec['type'] !== 'meter')
        <details class="form-chart-table mt-3 text-sm" @if($tableOpen) open @endif>
            <summary class="inline-flex cursor-pointer items-center gap-1.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <x-heroicon-o-table-cells class="h-4 w-4" aria-hidden="true" />
                {{ __('form_charts.show_table') }}
            </summary>
            <table class="mt-2 w-full">
                <caption class="sr-only">{{ $spec['title'] }}</caption>
                <thead>
                    <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th scope="col" class="py-1.5 text-start font-medium">{{ __('form_charts.answer') }}</th>
                        <th scope="col" class="py-1.5 text-end font-medium">{{ __('form_charts.count') }}</th>
                        <th scope="col" class="py-1.5 text-end font-medium">{{ __('form_charts.share') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($spec['labels'] as $i => $label)
                        <tr>
                            <td class="py-1.5 text-gray-700 dark:text-gray-200">{{ $label }}</td>
                            <td class="py-1.5 text-end tabular-nums text-gray-700 dark:text-gray-200" dir="ltr">{{ $number($spec['counts'][$i]) }}</td>
                            <td class="py-1.5 text-end tabular-nums text-gray-500 dark:text-gray-400" dir="ltr">{{ $number($spec['shares'][$i]) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>
