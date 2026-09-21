@props(['url', 'title', 'heading' => null, 'bare' => false])

<div @class(['card-warm p-5' => ! $bare])>
    @unless($bare)
        <h2 class="font-display text-base font-semibold text-warm-900">{{ $heading ?? __('listings.share_title') }}</h2>
    @endunless

    <ul @class(['flex flex-wrap items-center gap-2.5', 'mt-3' => ! $bare])>
        <li>
            <a href="https://wa.me/?text={{ urlencode($title . ' ' . $url) }}" target="_blank" rel="noopener noreferrer"
                aria-label="{{ __('listings.share_whatsapp') }}"
                class="flex h-10 w-10 items-center justify-center rounded-full bg-warm-100 text-warm-700 transition hover:bg-warm-600 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4.5 w-4.5" fill="currentColor">
                    <path d="M12.01 2C6.49 2 2 6.49 2 12.01c0 1.94.55 3.75 1.5 5.3L2 22l4.83-1.46a9.96 9.96 0 0 0 5.18 1.45h.01c5.52 0 10-4.49 10-10.01C22 6.49 17.53 2 12.01 2Zm0 18.18c-1.62 0-3.13-.44-4.43-1.2l-.32-.19-3.03.92.93-2.95-.2-.32a8.15 8.15 0 0 1-1.28-4.43c0-4.5 3.66-8.16 8.34-8.16 4.47 0 8.13 3.66 8.13 8.16 0 4.5-3.66 8.17-8.14 8.17Zm4.47-6.1c-.24-.12-1.44-.71-1.66-.79-.22-.08-.39-.12-.55.12-.16.24-.63.79-.78.95-.14.16-.28.18-.53.06-.24-.12-1.03-.38-1.96-1.21-.72-.64-1.21-1.44-1.36-1.68-.14-.24-.02-.37.11-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.55-1.33-.76-1.82-.2-.48-.4-.42-.55-.42h-.47c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.7 2.6 4.13 3.64.58.25 1.03.4 1.38.51.58.18 1.11.16 1.53.1.47-.07 1.44-.59 1.64-1.16.2-.57.2-1.05.14-1.16-.06-.11-.22-.17-.46-.29Z" />
                </svg>
            </a>
        </li>
        <li>
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($url) }}" target="_blank" rel="noopener noreferrer"
                aria-label="{{ __('listings.share_facebook') }}"
                class="flex h-10 w-10 items-center justify-center rounded-full bg-warm-100 text-warm-700 transition hover:bg-warm-600 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4.5 w-4.5" fill="currentColor">
                    <path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.51 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.91h-2.34V22c4.78-.76 8.44-4.92 8.44-9.94Z" />
                </svg>
            </a>
        </li>
        <li>
            <a href="https://twitter.com/intent/tweet?url={{ urlencode($url) }}&text={{ urlencode($title) }}" target="_blank" rel="noopener noreferrer"
                aria-label="{{ __('listings.share_x') }}"
                class="flex h-10 w-10 items-center justify-center rounded-full bg-warm-100 text-warm-700 transition hover:bg-warm-600 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4.5 w-4.5" fill="currentColor">
                    <path d="M18.24 2h3.06l-6.69 7.65L22.5 22h-6.17l-4.83-6.32L5.96 22H2.9l7.16-8.19L1.5 2h6.32l4.37 5.78L18.24 2Zm-1.08 18.17h1.7L7.9 3.75H6.08l11.08 16.42Z" />
                </svg>
            </a>
        </li>
        <li>
            <a href="https://t.me/share/url?url={{ urlencode($url) }}&text={{ urlencode($title) }}" target="_blank" rel="noopener noreferrer"
                aria-label="{{ __('listings.share_telegram') }}"
                class="flex h-10 w-10 items-center justify-center rounded-full bg-warm-100 text-warm-700 transition hover:bg-warm-600 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4.5 w-4.5" fill="currentColor">
                    <path d="M21.94 4.6 18.6 20.3c-.25 1.12-.9 1.39-1.83.87l-5.06-3.73-2.44 2.35c-.27.27-.5.5-1.02.5l.36-5.16 9.4-8.49c.41-.36-.09-.56-.63-.2L6.3 13.02l-5.02-1.57c-1.09-.34-1.11-1.09.23-1.61L20.6 3.15c.91-.34 1.7.2 1.34 1.45Z" />
                </svg>
            </a>
        </li>
        <li>
            <a href="mailto:?subject={{ urlencode($title) }}&body={{ urlencode($url) }}"
                aria-label="{{ __('listings.share_email') }}"
                class="flex h-10 w-10 items-center justify-center rounded-full bg-warm-100 text-warm-700 transition hover:bg-warm-600 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6.75A1.75 1.75 0 0 1 4.75 5h14.5A1.75 1.75 0 0 1 21 6.75v10.5A1.75 1.75 0 0 1 19.25 19H4.75A1.75 1.75 0 0 1 3 17.25V6.75Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6" />
                </svg>
            </a>
        </li>
        <li>
            <button type="button" data-copy-link="{{ $url }}"
                data-copy-label="{{ __('listings.share_copy_link') }}"
                data-copied-label="{{ __('listings.share_copied') }}"
                aria-label="{{ __('listings.share_copy_link') }}"
                class="flex h-10 w-10 items-center justify-center rounded-full bg-warm-100 text-warm-700 transition hover:bg-warm-600 hover:text-white">
                <svg data-copy-icon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757M10.81 15.313a4.5 4.5 0 0 1-1.242-7.244l4.5-4.5a4.5 4.5 0 0 1 6.364 6.364l-1.757 1.757" />
                </svg>
            </button>
        </li>
    </ul>
</div>
