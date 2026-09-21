<div class="space-y-6">
    @foreach($detail['runners'] as $runner)
        <article class="card-warm p-5 sm:p-6">
            <div class="flex items-start gap-4">
                <div class="min-w-12 rounded-xl bg-warm-600 px-3 py-2 text-center text-white">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide opacity-80">{{ __('racing.card_labels.no') }}</p>
                    <p class="font-display text-xl font-semibold">{{ $runner['no'] }}</p>
                </div>

                @include('site.racing._runner-summary', ['runner' => $runner])
            </div>

            <div class="mt-4">
                @if($runner['runs']['rows'])
                    @include('site.racing._table', ['table' => $runner['runs']])
                @else
                    <p class="rounded-2xl border border-dashed border-warm-300 bg-white/60 px-4 py-6 text-center text-sm text-warm-900/60">{{ __('racing.no_previous_runs') }}</p>
                @endif
            </div>
        </article>
    @endforeach
</div>
