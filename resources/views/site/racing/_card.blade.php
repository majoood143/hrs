<ul class="divide-y divide-warm-100 overflow-hidden rounded-2xl border border-warm-200/70 bg-white shadow-sm shadow-warm-900/5">
    @foreach($detail['runners'] as $runner)
        <li @class(['grid gap-4 p-4 sm:grid-cols-[auto_1fr_auto] sm:items-center sm:p-5', 'bg-red-50/70' => $runner['non_runner'] ?? false])>
            <div class="flex gap-2 text-center">
                <div class="min-w-14 rounded-xl bg-warm-600 px-3 py-2 text-white">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide opacity-80">{{ __('racing.card_labels.no') }}</p>
                    <p class="font-display text-xl font-semibold">{{ $runner['no'] }}</p>
                </div>
                <div class="min-w-14 rounded-xl bg-warm-50 px-3 py-2 text-warm-900">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-warm-700">{{ __('racing.card_labels.gate') }}</p>
                    <p class="font-display text-xl font-semibold">{{ $runner['gate'] }}</p>
                </div>
            </div>

            <div @class(['min-w-0', 'opacity-60' => $runner['non_runner'] ?? false])>
                @if($runner['non_runner'] ?? false)
                    <span class="mb-2 inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-red-700 ring-1 ring-red-200">
                        <x-heroicon-m-no-symbol class="h-3.5 w-3.5" aria-hidden="true" />
                        {{ __('racing.card_labels.non_runner') }}
                    </span>
                @endif
                @include('site.racing._runner-summary', ['runner' => $runner])
            </div>

            <div class="text-start sm:text-end">
                <p class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('racing.card_labels.weight') }}</p>
                <p class="font-display text-xl font-semibold text-warm-900" dir="ltr">{{ $runner['weight'] }}</p>
                @if($runner['weight_note'])
                    <p class="text-sm text-warm-900/60" dir="ltr">{{ $runner['weight_note'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ul>
