@if(!empty($data['items']))
<section class="mx-auto max-w-6xl px-6 py-14">
    <div data-reveal-group class="grid gap-8" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        @foreach($data['items'] as $item)
            @php
                $heading = \App\Support\Localized::value($item, 'heading');
                $text = \App\Support\Localized::value($item, 'text');
            @endphp
            <div data-reveal-item class="card-warm p-6">
                @if($heading)
                    <h3 class="font-display text-lg font-semibold text-warm-900">{{ $heading }}</h3>
                @endif
                @if($text)
                    <p class="mt-2 text-sm leading-relaxed text-warm-900/75">{{ $text }}</p>
                @endif
            </div>
        @endforeach
    </div>
</section>
@endif
