<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <div class="flex gap-4">
            <x-filament::button type="submit">
                Save Settings
            </x-filament::button>
            
            <x-filament::button color="gray" wire:click="testConnection" type="button">
                Test Connection
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>