{{-- horse block shared by the race card and the form guide: silks, horse, age/colour/sex, record, owner / trainer / jockey --}}
<div class="flex min-w-0 gap-4">
    @if($runner['image'])
        <img src="{{ route('racing.image', ['p' => $runner['image']]) }}" alt="" width="56" height="56" loading="lazy"
             class="h-14 w-14 shrink-0 rounded-xl border border-warm-200 object-cover">
    @endif

    <div class="min-w-0">
        <p class="font-display text-lg font-semibold text-warm-900"><x-racing.entity type="horse" :entity="$runner['horse']" /></p>

        @if($runner['meta'] || $runner['record'])
            <p class="text-sm text-warm-900/60">
                {{ implode(' · ', $runner['meta']) }}
                @if($runner['record'])
                    <span class="ms-2" dir="ltr" title="{{ __('racing.card_labels.record') }}">{{ $runner['record'] }}</span>
                @endif
            </p>
        @endif

        <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-sm">
            @foreach(['owner' => 'owner', 'trainer' => 'trainer', 'jockey' => 'jockey'] as $key => $type)
                @if($runner[$key])
                    <dt class="text-warm-900/60">{{ __('racing.card_labels.' . $key) }}</dt>
                    <dd><x-racing.entity :type="$type" :entity="$runner[$key]" /></dd>
                @endif
            @endforeach
        </dl>
    </div>
</div>
