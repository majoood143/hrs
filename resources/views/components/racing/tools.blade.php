@props(['title', 'url', 'pdf' => null])

@php
    // the language rides along, so whoever opens the shared link sees the page in the language it was shared in
    $shareUrl = $url . (str_contains($url, '?') ? '&' : '?') . 'lang=' . app()->getLocale();
    $pill = 'inline-flex min-h-10 items-center gap-2 rounded-full border border-warm-200 bg-white px-4 text-sm font-semibold text-warm-800 transition hover:border-warm-500 hover:text-warm-900 active:bg-warm-100';
@endphp

{{-- what a printout shows in place of the site header --}}
<p class="mb-4 hidden text-xs text-warm-900/60 print:block">
    {{ \App\Models\SiteSetting::siteName() }} &middot; <span dir="ltr">{{ $url }}</span> &middot; {{ now()->locale(app()->getLocale())->translatedFormat('j F Y') }}
</p>

<div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-3 print:hidden" data-racing-tools>
    <div class="flex flex-wrap items-center gap-2">
        {{-- needs JavaScript, so it only appears when the script is there to make it work --}}
        <button type="button" data-print hidden class="{{ $pill }}">
            @svg('heroicon-o-printer', 'h-4 w-4 shrink-0')
            {{ __('racing.tools.print') }}
        </button>

        @if($pdf)
            <a href="{{ $pdf }}" title="{{ __('racing.tools.pdf_title') }}" class="{{ $pill }}">
                @svg('heroicon-o-arrow-down-tray', 'h-4 w-4 shrink-0')
                {{ __('racing.tools.pdf') }}
            </a>
        @endif
    </div>

    <div class="flex items-center gap-3">
        <span class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('racing.tools.share') }}</span>
        <x-share-buttons :url="$shareUrl" :title="$title" bare />
    </div>
</div>
