@php
    $columnsPerRow = $data['columns_per_row'] ?? 'auto';
    $cardStyle = $data['card_style'] ?? 'card';
    $alignment = $data['alignment'] ?? 'left';

    $gridStyle = match ($columnsPerRow) {
        '2' => 'grid-template-columns: repeat(2, minmax(0, 1fr));',
        '3' => 'grid-template-columns: repeat(3, minmax(0, 1fr));',
        '4' => 'grid-template-columns: repeat(4, minmax(0, 1fr));',
        default => 'grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));',
    };

    $cardClasses = match ($cardStyle) {
        'minimal' => '',
        'bordered' => 'rounded-2xl border border-warm-900/10 p-6',
        default => 'card-warm p-6',
    };
@endphp
@if(!empty($data['items']))
<section class="mx-auto max-w-6xl px-6 py-14">
    <div data-reveal-group class="grid gap-8" style="{{ $gridStyle }}">
        @foreach($data['items'] as $item)
            @php
                $heading = \App\Support\Localized::value($item, 'heading');
                $text = \App\Support\Localized::value($item, 'text');
                $linkLabel = \App\Support\Localized::value($item, 'link_label');
                $linkUrl = $item['link_url'] ?? null;
                $mediaType = $item['media_type'] ?? 'none';
            @endphp
            <div data-reveal-item class="{{ $cardClasses }} {{ $alignment === 'center' ? 'flex flex-col items-center text-center' : '' }}">
                @if($mediaType === 'icon' && !empty($item['icon']))
                    <span class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-warm-100 text-warm-700">
                        @svg($item['icon'], 'h-6 w-6')
                    </span>
                @elseif($mediaType === 'image' && !empty($item['image']))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item['image']) }}"
                         alt="{{ $heading }}"
                         class="mb-4 h-12 w-auto object-contain" />
                @endif
                @if($heading)
                    <h3 class="font-display text-lg font-semibold text-warm-900">{{ $heading }}</h3>
                @endif
                @if($text)
                    <p class="mt-2 text-sm leading-relaxed text-warm-900/75">{{ $text }}</p>
                @endif
                @if($linkUrl)
                    <a href="{{ $linkUrl }}"
                       class="mt-4 inline-flex items-center gap-1 font-semibold text-warm-700 underline decoration-warm-300 underline-offset-4 hover:text-warm-800">
                        {{ $linkLabel ?: $linkUrl }}
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                @endif
            </div>
        @endforeach
    </div>
</section>
@endif
