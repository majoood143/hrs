@php
    $systemGroups = $this->systemInfoGroups();
    $usage = $this->usageStats();
@endphp
<x-filament-panels::page>
    <div class="flex flex-col gap-y-6">
        <x-filament::section>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span
                        @class([
                            'inline-flex h-3 w-3 rounded-full',
                            'bg-danger-500' => $this->isDown(),
                            'bg-success-500' => ! $this->isDown(),
                        ])
                    ></span>

                    <div>
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $this->isDown() ? __('maintenance.status.down') : __('maintenance.status.up') }}
                        </p>

                        @if ($this->isDown() && $this->downSince())
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('maintenance.status.since', ['time' => $this->downSince()->diffForHumans()]) }}
                            </p>
                        @endif
                    </div>
                </div>

                @if ($this->isDown())
                    <x-filament::button color="success" wire:click="disable"
                        wire:confirm="{{ __('maintenance.confirm.disable') }}">
                        {{ __('maintenance.actions.disable') }}
                    </x-filament::button>
                @else
                    <x-filament::button color="danger" wire:click="enable"
                        wire:confirm="{{ __('maintenance.confirm.enable') }}">
                        {{ __('maintenance.actions.enable') }}
                    </x-filament::button>
                @endif
            </div>

            @if ($this->isDown() && $this->bypassUrl())
                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                    <p class="font-medium text-gray-950 dark:text-white">{{ __('maintenance.status.bypass_link') }}</p>
                    <p class="mt-1 break-all font-mono text-xs text-gray-600 dark:text-gray-300">
                        {{ $this->bypassUrl() }}
                    </p>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section
            :heading="__('maintenance.system.title')"
            :description="__('maintenance.system.description')"
            icon="heroicon-o-information-circle"
            icon-color="success"
            :divided="true"
        >
            <div class="flex flex-col gap-y-6">
                @foreach ($systemGroups as $group)
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            {{ $group['label'] }}
                        </p>
                        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            @foreach ($group['items'] as $item)
                                <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ $item['label'] }}
                                    </p>
                                    <div class="mt-1">
                                        @if (array_key_exists('badge', $item))
                                            <x-filament::badge :color="$item['color']">
                                                {{ $item['badge'] }}
                                            </x-filament::badge>
                                        @else
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                                {{ $item['value'] }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 border-t border-gray-100 pt-6 sm:grid-cols-3 dark:border-white/10">
                <div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('maintenance.system.log_file') }}</span>
                        <span class="font-medium text-gray-950 dark:text-white">{{ $this::formatBytes($usage['log']['bytes']) }}</span>
                    </div>
                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-full rounded-full bg-danger-500" style="width: {{ $usage['log']['percent'] }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('maintenance.system.cache_storage') }}</span>
                        <span class="font-medium text-gray-950 dark:text-white">{{ $this::formatBytes($usage['cache']['bytes']) }}</span>
                    </div>
                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-full rounded-full bg-info-500" style="width: {{ $usage['cache']['percent'] }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('maintenance.system.disk_usage') }}</span>
                        <span class="font-medium text-gray-950 dark:text-white">
                            {{ __('maintenance.system.disk_usage_detail', [
                                'free' => $this::formatBytes($usage['disk']['free']),
                                'total' => $this::formatBytes($usage['disk']['total']),
                                'percent' => $usage['disk']['percent'],
                            ]) }}
                        </span>
                    </div>
                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div
                            @class([
                                'h-full rounded-full',
                                'bg-danger-500' => $usage['disk']['percent'] >= 90,
                                'bg-warning-500' => $usage['disk']['percent'] >= 75 && $usage['disk']['percent'] < 90,
                                'bg-success-500' => $usage['disk']['percent'] < 75,
                            ])
                            style="width: {{ min($usage['disk']['percent'], 100) }}%"
                        ></div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-4 text-sm">
                <a href="{{ url('/admin/health-check-results') }}" class="font-medium text-primary-600 hover:underline">
                    {{ __('maintenance.system.view_health_report') }} &rarr;
                </a>
                <a href="{{ url('/admin/backups') }}" class="font-medium text-primary-600 hover:underline">
                    {{ __('maintenance.system.view_backups') }} &rarr;
                </a>
            </div>
        </x-filament::section>

        <x-filament::section
            :heading="__('maintenance.quick_actions.title')"
            :description="__('maintenance.quick_actions.description')"
        >
            <div class="flex flex-wrap gap-2">
                <x-filament::button size="sm" color="gray" wire:click="runArtisan('cache:clear')">
                    {{ __('maintenance.quick_actions.cache_clear') }}
                </x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="runArtisan('config:clear')">
                    {{ __('maintenance.quick_actions.config_clear') }}
                </x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="runArtisan('route:clear')">
                    {{ __('maintenance.quick_actions.route_clear') }}
                </x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="runArtisan('view:clear')">
                    {{ __('maintenance.quick_actions.view_clear') }}
                </x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="runArtisan('optimize')">
                    {{ __('maintenance.quick_actions.optimize') }}
                </x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="runArtisan('queue:restart')">
                    {{ __('maintenance.quick_actions.queue_restart') }}
                </x-filament::button>
                @unless ($this->storageLinked())
                    <x-filament::button size="sm" color="warning" wire:click="runArtisan('storage:link')">
                        {{ __('maintenance.quick_actions.storage_link') }}
                    </x-filament::button>
                @endunless
            </div>
        </x-filament::section>

        <form wire:submit.prevent>
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button type="button" color="danger" wire:click="enable"
                    wire:confirm="{{ __('maintenance.confirm.enable') }}">
                    {{ $this->isDown() ? __('maintenance.actions.update') : __('maintenance.actions.enable') }}
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
