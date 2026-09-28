<x-filament-panels::page>
    <div class="flex flex-col gap-y-6">
        {{ $this->form }}

        @if($statement = $this->statement())
            @include('filament.stable.partials.statement-body', ['statement' => $statement])
        @else
            <x-filament::section>
                <p class="text-center text-sm text-gray-500 dark:text-gray-400">{{ __('stable_statement.admin.pick_stable') }}</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
