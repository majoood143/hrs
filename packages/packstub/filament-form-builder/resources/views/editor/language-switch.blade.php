{{--
    The editor's language switch (EditorLanguages). The store is created here, before the
    inputs that read it; an input reads it as `$store.fbEditorLang?.only`, so it shows until then.
--}}
<div
    x-data
    x-init="
        if (! Alpine.store('fbEditorLang')) {
            let saved = '';
            try { saved = localStorage.getItem('fbEditorLang') || ''; } catch (e) {}
            Alpine.store('fbEditorLang', {
                only: saved,
                set(code) {
                    this.only = code;
                    try { localStorage.setItem('fbEditorLang', code); } catch (e) {}
                },
            });
            window.addEventListener('form-validation-error', () => Alpine.store('fbEditorLang').only = '');
        }
    "
    style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;"
>
    <style>
        .fb-lang-grid:has(> * > .fi-hidden) > * > :not(.fi-hidden) { grid-column: 1 / -1 !important; }
    </style>

    <span style="font-size: 0.875rem; font-weight: 500;">
        {{ __('packstub-form-builder::form-builder.editor.languages') }}
    </span>

    <x-filament::tabs>
        <x-filament::tabs.item
            icon="heroicon-o-language"
            alpine-active="! $store.fbEditorLang?.only"
            x-on:click="$store.fbEditorLang.set('')"
        >
            {{ __('packstub-form-builder::form-builder.editor.languages_all') }}
        </x-filament::tabs.item>
        @foreach ($locales as $code => $meta)
            <x-filament::tabs.item
                :alpine-active="'$store.fbEditorLang?.only === ' . \Illuminate\Support\Js::from($code)"
                x-on:click="$store.fbEditorLang.set({{ \Illuminate\Support\Js::from($code) }})"
            >
                {{ $meta['native'] ?? strtoupper($code) }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>
</div>
