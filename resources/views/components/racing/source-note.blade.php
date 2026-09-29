@props(['short' => false])

@php
    $sourceUrl = config('racing.source_url');
    $link = '<a href="' . e($sourceUrl) . '" target="_blank" rel="noopener" dir="ltr" class="font-medium text-warm-900/80 underline decoration-warm-300 underline-offset-2 hover:text-warm-900">'
        . e(preg_replace('#^https?://(www\.)?#', '', rtrim($sourceUrl, '/'))) . '</a>';
    // escape the sentence first, then drop the link in, so only our own markup is raw
    $text = str_replace('__SITE__', $link, e(__('racing.source.' . ($short ? 'short' : 'full'), ['site' => '__SITE__'])));
@endphp

<p {{ $attributes->class(['flex items-start justify-center gap-2 text-center text-xs text-warm-900/60']) }}>
    <x-heroicon-o-information-circle class="mt-px h-4 w-4 shrink-0" aria-hidden="true" />
    <span>{!! $text !!}</span>
</p>
