{{-- What a ticket's QR code opens for anyone but the stable's staff: which stable, when, and the way in for each side. --}}
<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-xl px-6 py-14 text-center">
        <p class="section-eyebrow flex items-center justify-center gap-1.5">
            <x-heroicon-o-ticket class="h-4 w-4" aria-hidden="true" />{{ __('booking_ticket.title') }}
        </p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900" dir="ltr">{{ $booking->reference }}</h1>
        <p class="mt-3 text-warm-700">{{ __('booking_ticket.landing.intro', ['stable' => $booking->stable?->name]) }}</p>
        @if($booking->slot)
            <p class="mt-1 text-sm text-warm-700">{{ $booking->slot->startsAt()->locale(app()->getLocale())->translatedFormat('l j F Y') }} · <span dir="ltr">{{ $booking->slot->timeRange() }}</span></p>
        @endif

        <div class="mt-8 flex flex-col items-center gap-4">
            <a href="{{ $staffUrl }}" class="btn-warm">
                <x-heroicon-o-qr-code class="h-5 w-5" aria-hidden="true" />{{ __('booking_ticket.landing.staff') }}
            </a>
            @if($booking->order)
                <a href="{{ route('account.orders.show', $booking->order->order_number) }}" class="text-sm font-semibold text-warm-700 underline hover:text-warm-900">{{ __('booking_ticket.landing.customer') }}</a>
            @endif
        </div>
    </div>
</x-layouts.site>
