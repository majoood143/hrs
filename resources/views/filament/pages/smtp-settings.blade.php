<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex gap-4 mt-6">
            <x-filament::button type="submit" icon="heroicon-o-check">
                {{ __('admin_smtp_settings.actions.save') }}
            </x-filament::button>

            <x-filament::button color="gray" wire:click="testConnection" type="button" icon="heroicon-o-paper-airplane">
                {{ __('admin_smtp_settings.actions.test') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
