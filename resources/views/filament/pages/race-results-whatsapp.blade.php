<x-filament-panels::page>
    <x-filament::section>
        <p class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-300">
            <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="mt-0.5 h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
            <span>{{ $this->autoStatus() }}</span>
        </p>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
