@props(['urls', 'locale'])

{{--
    The editor's preview: one sandboxed iframe per language (no allow-forms, so nothing can
    be sent from it), loaded the first time its tab is opened, at desktop or phone width.
    Inline styles: the package's views are not scanned by the admin theme's Tailwind build.
--}}
<div
    x-data="{ locale: @js($locale), loaded: { [@js($locale)]: true }, phone: false }"
    style="display: flex; flex-direction: column; gap: 0.75rem;"
>
    <p style="margin: 0; font-size: 0.875rem; opacity: 0.75;">
        {{ __('packstub-form-builder::form-builder.preview.note') }}
    </p>

    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: space-between;">
        <x-filament::tabs>
            @foreach ($urls as $code => $url)
                <x-filament::tabs.item
                    :alpine-active="'locale === ' . \Illuminate\Support\Js::from($code)"
                    x-on:click="locale = {{ \Illuminate\Support\Js::from($code) }}; loaded[{{ \Illuminate\Support\Js::from($code) }}] = true"
                >
                    {{ config("languages.available.{$code}.name", strtoupper($code)) }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <x-filament::tabs>
            <x-filament::tabs.item icon="heroicon-o-computer-desktop" alpine-active="! phone" x-on:click="phone = false">
                {{ __('packstub-form-builder::form-builder.preview.desktop') }}
            </x-filament::tabs.item>
            <x-filament::tabs.item icon="heroicon-o-device-phone-mobile" alpine-active="phone" x-on:click="phone = true">
                {{ __('packstub-form-builder::form-builder.preview.phone') }}
            </x-filament::tabs.item>
        </x-filament::tabs>
    </div>

    @foreach ($urls as $code => $url)
        <div x-show="locale === {{ \Illuminate\Support\Js::from($code) }}" x-cloak style="display: flex; justify-content: center;">
            <template x-if="loaded[{{ \Illuminate\Support\Js::from($code) }}]">
                <iframe
                    src="{{ $url }}"
                    title="{{ __('packstub-form-builder::form-builder.preview.heading') }} ({{ strtoupper($code) }})"
                    sandbox="allow-scripts allow-same-origin"
                    loading="lazy"
                    x-bind:style="'border: 1px solid rgba(128,128,128,.3); border-radius: 0.75rem; background: #fff; height: 75vh; width: ' + (phone ? '390px' : '100%') + '; max-width: 100%;'"
                ></iframe>
            </template>
        </div>
    @endforeach
</div>
