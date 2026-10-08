{{--
    Share bar for the Media Library explorer. Selection lives in the plugin's Alpine store
    ($store.feSel), so the bar reacts instantly; links come from App\Support\MediaLinks.
    Keyed by the file list, so Alpine starts fresh after a navigation, upload or rename.
--}}
@php
    $shareTexts = [
        'copied' => __('admin_media_library.share.copied'),
        'copiedMany' => __('admin_media_library.share.copied_many'),
        'copyFailed' => __('admin_media_library.share.copy_failed'),
    ];
@endphp
<div
    wire:key="media-share-{{ md5(json_encode($shareFiles)) }}"
    x-data="{
        links: @js((object) $shareFiles),
        texts: @js($shareTexts),
        copied: false,
        sheet: false,
        qr: null,
        qrLoading: false,
        timer: null,
        get selected() {
            return (this.$store.feSel?.files || []).map((id) => this.links[id]).filter(Boolean);
        },
        get one() {
            return this.selected.length === 1 ? this.selected[0] : null;
        },
        get linked() {
            return this.selected.filter((file) => file.url);
        },
        get canNativeShare() {
            return typeof navigator.share === 'function';
        },
        async copy(text, many = false) {
            let ok = true;
            try {
                await navigator.clipboard.writeText(text);
            } catch (e) {
                const area = document.createElement('textarea');
                area.value = text;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                try { ok = document.execCommand('copy'); } catch (e2) { ok = false; }
                area.remove();
            }

            if (! ok) {
                new FilamentNotification().title(this.texts.copyFailed).warning().send();
                return;
            }

            this.copied = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.copied = false, 2000);
            new FilamentNotification().title(many ? this.texts.copiedMany : this.texts.copied).success().send();
        },
        copyAll() {
            this.copy(this.linked.map((file) => file.url).join('\n'), true);
        },
        async openSheet() {
            this.sheet = true;
            this.qr = null;
            this.qrLoading = true;
            const id = this.one?.id;
            try {
                const svg = await this.$wire.qrSvg(id);
                if (this.one?.id === id) this.qr = svg;
            } finally {
                this.qrLoading = false;
            }
        },
        qrDownloadUrl() {
            return this.qr ? 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(this.qr) : '#';
        },
        qrFileName(file) {
            return (file.name.replace(/\.[^.]+$/, '') || 'file') + '-qr.svg';
        },
        async nativeShare(file) {
            try {
                await navigator.share({ title: file.name, url: file.url });
            } catch (e) {
                // closed by the user, nothing to do
            }
        },
        whatsappUrl(file) {
            return 'https://wa.me/?text=' + encodeURIComponent(file.name + '\n' + file.url);
        },
        mailUrl(file) {
            return 'mailto:?subject=' + encodeURIComponent(file.name) + '&body=' + encodeURIComponent(file.url);
        },
    }"
    @keydown.escape.window="sheet = false"
>
    {{-- Bar: one or more files selected --}}
    <div
        x-show="selected.length > 0"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="mt-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900"
        role="region"
        aria-label="{{ __('admin_media_library.share.region') }}"
    >
        {{-- One file --}}
        <template x-if="one">
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2 text-sm">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                        <x-heroicon-o-photo x-show="one.image" class="h-5 w-5" aria-hidden="true" />
                        <x-heroicon-o-document x-show="! one.image" class="h-5 w-5" aria-hidden="true" />
                    </span>
                    <span class="min-w-0 truncate font-medium text-gray-950 dark:text-white" x-text="one.name"></span>
                    <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400" x-text="one.size"></span>
                </div>

                <template x-if="one.url">
                    <div class="flex flex-col gap-2">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <label class="sr-only" for="media-share-url">{{ __('admin_media_library.share.link') }}</label>
                            <div class="flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                                <x-heroicon-o-link class="h-4 w-4 shrink-0 text-gray-400" aria-hidden="true" />
                                <input
                                    id="media-share-url"
                                    type="text"
                                    readonly
                                    dir="ltr"
                                    :value="one.url"
                                    @focus="$event.target.select()"
                                    class="w-full min-w-0 border-0 bg-transparent p-0 font-mono text-xs text-gray-700 focus:ring-0 dark:text-gray-200"
                                >
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <x-filament::button size="sm" x-on:click="copy(one.url)">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-heroicon-o-clipboard-document x-show="! copied" class="h-4 w-4" aria-hidden="true" />
                                        <x-heroicon-o-check x-show="copied" x-cloak class="h-4 w-4" aria-hidden="true" />
                                        <span x-text="copied ? @js(__('admin_media_library.share.copied_short')) : @js(__('admin_media_library.share.copy'))"></span>
                                    </span>
                                </x-filament::button>
                                <x-filament::button size="sm" color="gray" icon="heroicon-o-share" x-on:click="openSheet()">
                                    {{ __('admin_media_library.share.share') }}
                                </x-filament::button>
                                <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-top-right-on-square" tag="a" x-bind:href="one.url" target="_blank" rel="noopener">
                                    {{ __('admin_media_library.share.open') }}
                                </x-filament::button>
                            </div>
                        </div>
                        <p class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <x-heroicon-o-globe-alt class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                            {{ __('admin_media_library.share.public_notice') }}
                        </p>
                    </div>
                </template>

                <template x-if="! one.url">
                    <p class="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-300">
                        <x-heroicon-o-lock-closed class="h-4 w-4 shrink-0" aria-hidden="true" />
                        {{ __('admin_media_library.share.no_link') }}
                    </p>
                </template>
            </div>
        </template>

        {{-- Several files --}}
        <template x-if="selected.length > 1">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="flex items-center gap-2 text-sm font-medium text-gray-950 dark:text-white">
                    <x-heroicon-o-document-duplicate class="h-5 w-5 text-primary-600 dark:text-primary-400" aria-hidden="true" />
                    <span x-text="@js(__('admin_media_library.share.selected_many', ['count' => '__N__'])).replace('__N__', selected.length)"></span>
                </span>
                <x-filament::button size="sm" x-show="linked.length > 0" x-on:click="copyAll()">
                    <span class="inline-flex items-center gap-1.5">
                        <x-heroicon-o-clipboard-document x-show="! copied" class="h-4 w-4" aria-hidden="true" />
                        <x-heroicon-o-check x-show="copied" x-cloak class="h-4 w-4" aria-hidden="true" />
                        <span x-text="copied ? @js(__('admin_media_library.share.copied_short')) : @js(__('admin_media_library.share.copy_many', ['count' => '__N__'])).replace('__N__', linked.length)"></span>
                    </span>
                </x-filament::button>
            </div>
        </template>

        <span class="sr-only" aria-live="polite" x-text="copied ? (selected.length > 1 ? texts.copiedMany : texts.copied) : ''"></span>
    </div>

    {{-- Share sheet --}}
    <div
        x-show="sheet && one && one.url"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-end justify-center bg-gray-950/50 p-4 sm:items-center"
        @click.self="sheet = false"
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="media-share-title"
            class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            x-trap.noscroll="sheet"
        >
            <template x-if="one && one.url">
                <div class="flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 id="media-share-title" class="flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                                <x-heroicon-o-share class="h-5 w-5 text-primary-600 dark:text-primary-400" aria-hidden="true" />
                                {{ __('admin_media_library.share.sheet_title') }}
                            </h2>
                            <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400" x-text="one.name"></p>
                        </div>
                        <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5" @click="sheet = false">
                            <x-heroicon-o-x-mark class="h-5 w-5" aria-hidden="true" />
                            <span class="sr-only">{{ __('admin_media_library.share.close') }}</span>
                        </button>
                    </div>

                    <template x-if="one.image">
                        <img :src="one.url" alt="" class="max-h-40 w-full rounded-lg bg-gray-50 object-contain ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    </template>

                    <div class="flex items-center gap-2">
                        <div class="flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                            <x-heroicon-o-link class="h-4 w-4 shrink-0 text-gray-400" aria-hidden="true" />
                            <input type="text" readonly dir="ltr" :value="one.url" @focus="$event.target.select()" aria-label="{{ __('admin_media_library.share.link') }}" class="w-full min-w-0 border-0 bg-transparent p-0 font-mono text-xs text-gray-700 focus:ring-0 dark:text-gray-200">
                        </div>
                        <x-filament::button size="sm" x-on:click="copy(one.url)">
                            <span class="inline-flex items-center gap-1.5">
                                <x-heroicon-o-clipboard-document x-show="! copied" class="h-4 w-4" aria-hidden="true" />
                                <x-heroicon-o-check x-show="copied" x-cloak class="h-4 w-4" aria-hidden="true" />
                                <span x-text="copied ? @js(__('admin_media_library.share.copied_short')) : @js(__('admin_media_library.share.copy'))"></span>
                            </span>
                        </x-filament::button>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <a :href="whatsappUrl(one)" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 rounded-lg border border-gray-200 p-3 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                            <x-heroicon-o-chat-bubble-left-right class="h-6 w-6 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                            {{ __('admin_media_library.share.whatsapp') }}
                        </a>
                        <a :href="mailUrl(one)" class="flex flex-col items-center gap-1.5 rounded-lg border border-gray-200 p-3 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                            <x-heroicon-o-envelope class="h-6 w-6 text-sky-600 dark:text-sky-400" aria-hidden="true" />
                            {{ __('admin_media_library.share.email') }}
                        </a>
                        <button type="button" x-show="canNativeShare" @click="nativeShare(one)" class="flex flex-col items-center gap-1.5 rounded-lg border border-gray-200 p-3 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                            <x-heroicon-o-ellipsis-horizontal-circle class="h-6 w-6 text-gray-500" aria-hidden="true" />
                            {{ __('admin_media_library.share.more') }}
                        </button>
                        <a x-show="! canNativeShare" :href="one.url" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 rounded-lg border border-gray-200 p-3 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                            <x-heroicon-o-arrow-top-right-on-square class="h-6 w-6 text-gray-500 rtl:-scale-x-100" aria-hidden="true" />
                            {{ __('admin_media_library.share.open') }}
                        </a>
                    </div>

                    {{-- QR code: scanning it opens the same link --}}
                    <div class="flex items-center gap-4 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-md bg-white ring-1 ring-gray-950/5">
                            <div x-show="qr" x-html="qr" class="h-24 w-24 p-1 [&>svg]:h-full [&>svg]:w-full"></div>
                            <x-filament::loading-indicator x-show="qrLoading" class="h-5 w-5 text-gray-400" />
                            <x-heroicon-o-qr-code x-show="! qr && ! qrLoading" class="h-8 w-8 text-gray-300" aria-hidden="true" />
                        </div>
                        <div class="flex min-w-0 flex-col gap-2">
                            <p class="flex items-center gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                                <x-heroicon-o-qr-code class="h-4 w-4 text-gray-500" aria-hidden="true" />
                                {{ __('admin_media_library.share.qr') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin_media_library.share.qr_hint') }}</p>
                            <a x-show="qr" :href="qrDownloadUrl()" :download="qrFileName(one)" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" aria-hidden="true" />
                                {{ __('admin_media_library.share.qr_download') }}
                            </a>
                        </div>
                    </div>

                    <p class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-globe-alt class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                        {{ __('admin_media_library.share.public_notice') }}
                    </p>
                </div>
            </template>
        </div>
    </div>
</div>
