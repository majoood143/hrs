@php
    use App\Enums\StableApprovalStatus;

    $tone = match ($status) {
        StableApprovalStatus::Approved => 'success',
        StableApprovalStatus::Pending => 'warning',
        default => 'danger',
    };
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <div @class([
                'flex h-12 w-12 shrink-0 items-center justify-center rounded-full',
                'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' => $tone === 'success',
                'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400' => $tone === 'warning',
                'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' => $tone === 'danger',
            ])>
                <x-filament::icon :icon="$status->icon()" class="h-7 w-7" aria-hidden="true" />
            </div>

            <div class="flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $stable->name }}</h2>
                    <x-filament::badge :color="$status->color()" :icon="$status->icon()">{{ $status->label() }}</x-filament::badge>
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('stable_panel.dashboard.status_text.'.$status->value) }}</p>

                @if ($status !== StableApprovalStatus::Approved && filled($stable->rejection_reason))
                    <p class="text-sm text-red-700 dark:text-red-300">
                        <span class="font-medium">{{ __('stable_panel.fields.reason') }}:</span> {{ $stable->rejection_reason }}
                    </p>
                @endif

                @if ($status === StableApprovalStatus::Approved && $stable->formatted_commission)
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        <span class="font-medium">{{ __('stable_panel.fields.commission') }}:</span> {{ $stable->formatted_commission }}
                    </p>
                @endif

                <div class="flex flex-wrap gap-2 pt-1">
                    @unless ($hasOfferings)
                        <x-filament::button tag="a" :href="$offeringsUrl" icon="heroicon-o-plus" size="sm">
                            {{ __('stable_panel.dashboard.add_offering') }}
                        </x-filament::button>
                    @endunless
                    @if ($hasOfferings && ! $hasSchedules)
                        <x-filament::button tag="a" :href="$schedulesUrl" icon="heroicon-o-calendar-days" size="sm">
                            {{ __('stable_panel.dashboard.add_schedule') }}
                        </x-filament::button>
                    @endif
                    @if ($publicUrl)
                        <x-filament::button tag="a" :href="$publicUrl" target="_blank" color="gray" icon="heroicon-o-arrow-top-right-on-square" size="sm">
                            {{ __('stable_panel.dashboard.view_on_site') }}
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
