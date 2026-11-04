@php
    $booking = $this->booking();
    $elsewhere = $booking ? null : $this->elsewhere();
    $state = $booking ? \App\Filament\Stable\Pages\CheckIn::state($booking) : null;
    $tones = [
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
        'danger' => 'bg-red-50 text-red-800 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30',
    ];
@endphp

<x-filament-panels::page>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-y-6">
        @if($this->done && $booking)
            <x-filament::section>
                <div class="flex flex-col items-center gap-3 py-6 text-center">
                    <x-filament::icon icon="heroicon-o-check-badge" class="h-16 w-16 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                    <p class="text-xl font-semibold text-gray-950 dark:text-white" role="status">{{ __('booking_ticket.check_in.done') }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $booking->order?->customer_name }} · <span dir="ltr">{{ $booking->reference }}</span></p>
                    <x-filament::button wire:click="another" icon="heroicon-o-qr-code" size="lg" class="mt-2">{{ __('booking_ticket.check_in.another') }}</x-filament::button>
                </div>
            </x-filament::section>
        @else
            <form wire:submit="find" class="flex flex-col gap-4">
                {{ $this->form }}
                <div>
                    <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">{{ __('booking_ticket.check_in.find') }}</x-filament::button>
                </div>
            </form>

            @if($this->reference && ! $booking)
                <x-filament::section>
                    @if($elsewhere)
                        <p class="text-sm text-gray-700 dark:text-gray-200">{{ __('booking_ticket.check_in.other_stable', ['stable' => $elsewhere->stable->name]) }}</p>
                        <x-filament::button tag="a" class="mt-3" icon="heroicon-o-arrows-right-left"
                            :href="\App\Filament\Stable\Pages\CheckIn::getUrl(['reference' => $elsewhere->reference], tenant: $elsewhere->stable)">
                            {{ __('booking_ticket.check_in.open_other', ['stable' => $elsewhere->stable->name]) }}
                        </x-filament::button>
                    @else
                        <p class="flex items-center gap-2 text-sm text-red-700 dark:text-red-300" role="alert">
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5" aria-hidden="true" />
                            {{ __('booking_ticket.check_in.not_found', ['reference' => $this->reference]) }}
                        </p>
                    @endif
                </x-filament::section>
            @endif

            @if($booking && $state)
                <x-filament::section>
                    <div @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-base font-semibold ring-1', $tones[$state['tone']] ?? $tones['warning']]) role="status">
                        <x-filament::icon :icon="$state['icon']" class="h-7 w-7 shrink-0" aria-hidden="true" />
                        <span>{{ $state['text'] }}</span>
                    </div>

                    @if($state['note'])
                        <p class="mt-3 flex items-center gap-2 rounded-xl bg-sky-50 px-4 py-3 text-sm font-semibold text-sky-900 dark:bg-sky-500/10 dark:text-sky-300">
                            <x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5 shrink-0" aria-hidden="true" />{{ $state['note'] }}
                        </p>
                    @endif

                    <dl class="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('booking_ticket.customer') }}</dt>
                            <dd class="font-semibold text-gray-950 dark:text-white">{{ $booking->order?->customer_name ?: '—' }}
                                @if($booking->order?->customer_phone)<span class="font-normal text-gray-500" dir="ltr"> +{{ $booking->order->customer_phone }}</span>@endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('booking_ticket.service') }}</dt>
                            <dd class="font-semibold text-gray-950 dark:text-white">{{ $booking->offering?->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('booking_ticket.date') }}</dt>
                            <dd class="font-semibold text-gray-950 dark:text-white">{{ $booking->slot?->startsAt()->locale(app()->getLocale())->translatedFormat('l j F') }} · <span dir="ltr">{{ $booking->slot?->timeRange() }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('booking_ticket.riders') }}</dt>
                            <dd class="font-semibold text-gray-950 dark:text-white">{{ trans_choice('stable_bookings.riders_count', $booking->riders, ['count' => $booking->riders]) }}
                                @if($booking->riderNames())<span class="block font-normal text-gray-600 dark:text-gray-300">{{ $booking->riderNames() }}</span>@endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('booking_ticket.payment') }}</dt>
                            <dd class="font-semibold text-gray-950 dark:text-white">{{ \App\Filament\Stable\Resources\StableBookings\StableBookingResource::paymentLabel($booking) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('stable_bookings.fields.reference') }}</dt>
                            <dd class="font-mono font-semibold text-gray-950 dark:text-white" dir="ltr">{{ $booking->reference }}</dd>
                        </div>
                    </dl>

                    @if($state['can'])
                        <x-filament::button wire:click="checkIn" wire:loading.attr="disabled" color="success" size="xl" icon="heroicon-o-check-badge" class="mt-6 w-full">
                            {{ __('booking_ticket.check_in.check_in') }}
                        </x-filament::button>
                    @endif
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
