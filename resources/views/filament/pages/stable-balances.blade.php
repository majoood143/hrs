@php
    $totals = $this->totals();
@endphp
<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-filament::section>
            <p class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-arrow-up-right" class="h-4 w-4" aria-hidden="true" />{{ __('stable_statement.admin.total_we_owe') }}
            </p>
            <p class="mt-1 text-2xl font-semibold text-emerald-700 dark:text-emerald-400" dir="ltr">{{ \App\Support\Money::formatHtml($totals['we_owe']) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('stable_statement.admin.total_we_owe_hint') }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-arrow-down-left" class="h-4 w-4" aria-hidden="true" />{{ __('stable_statement.admin.total_they_owe') }}
            </p>
            <p class="mt-1 text-2xl font-semibold text-amber-700 dark:text-amber-400" dir="ltr">{{ \App\Support\Money::formatHtml($totals['they_owe']) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('stable_statement.admin.total_they_owe_hint') }}</p>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
