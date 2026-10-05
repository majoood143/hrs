@php
    $block = \App\Support\FormChartBlock::resolve($data);
@endphp

@if($block)
    @vite('resources/js/form-charts.js')

    <section class="mx-auto max-w-4xl px-6 py-12" data-reveal>
        <div class="text-center">
            <h2 class="inline-flex items-center gap-2 font-display text-3xl font-semibold sm:text-4xl">
                <x-heroicon-o-chart-pie class="h-7 w-7 text-warm-600" aria-hidden="true" />
                {{ $block['heading'] }}
            </h2>
            @if($block['subheading'])
                <p class="mx-auto mt-3 max-w-xl text-balance opacity-80">{{ $block['subheading'] }}</p>
            @endif
        </div>

        <div class="mt-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-warm-200 sm:p-8">
            @if($block['spec'])
                <x-form-insights.chart :id="'block-'.md5(json_encode($data))" :spec="$block['spec']" :table="$block['table']" />

                <p class="mt-4 flex items-center justify-center gap-1.5 text-sm text-warm-700/80">
                    <x-heroicon-o-users class="h-4 w-4" aria-hidden="true" />
                    {{ __('form_charts.based_on', ['count' => number_format($block['answered'])]) }}
                </p>
            @else
                <div class="flex flex-col items-center gap-2 py-8 text-center text-warm-700/80">
                    <x-heroicon-o-clock class="h-8 w-8 text-warm-400" aria-hidden="true" />
                    <p>{{ __('form_charts.waiting', ['count' => $block['waiting']]) }}</p>
                </div>
            @endif
        </div>
    </section>
@endif
