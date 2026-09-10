<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex gap-4 mt-6">
            <x-filament::button type="submit">
                {{ __('cms_settings.actions.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
