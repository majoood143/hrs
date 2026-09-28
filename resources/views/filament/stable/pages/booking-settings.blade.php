<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex gap-4">
            <x-filament::button type="submit" icon="heroicon-o-check">
                {{ __('stable_panel.settings.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
