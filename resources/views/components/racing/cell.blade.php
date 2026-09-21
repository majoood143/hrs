@props(['cell'])

@php
    $link = $cell['link'] ?? null;
    $href = match (true) {
        ! $link => null,
        $link['type'] === 'race' => route('racing.race', $link['id']),
        in_array($link['type'], ['horse', 'owner', 'jockey', 'trainer'], true) => route('racing.profile', [$link['type'], $link['id']]),
        default => null,
    };
@endphp

@if($href)
    <a href="{{ $href }}"
       class="font-medium text-warm-700 underline decoration-warm-300 underline-offset-4 hover:text-warm-900">{{ $cell['text'] }}</a>
@else
    {{ $cell['text'] }}
@endif
