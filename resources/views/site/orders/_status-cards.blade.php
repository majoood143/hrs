{{-- The order and payment status as two cards, each with an icon tinted by the status' colour. --}}
@php
    $tones = [
        'success' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
        'info' => 'bg-sky-50 text-sky-600 ring-sky-100',
        'warning' => 'bg-amber-50 text-amber-600 ring-amber-100',
        'danger' => 'bg-red-50 text-red-600 ring-red-100',
        'gray' => 'bg-warm-50 text-warm-600 ring-warm-100',
    ];
    $statusIcon = match ($order->status) {
        \App\Enums\OrderStatus::Completed => 'heroicon-o-check-circle',
        \App\Enums\OrderStatus::InReview => 'heroicon-o-magnifying-glass',
        \App\Enums\OrderStatus::Processing => 'heroicon-o-arrow-path',
        \App\Enums\OrderStatus::PendingPayment => 'heroicon-o-clock',
        \App\Enums\OrderStatus::Rejected, \App\Enums\OrderStatus::Cancelled => 'heroicon-o-x-circle',
        default => 'heroicon-o-inbox-arrow-down',
    };
    $paymentIcon = match ($order->payment_status) {
        \App\Enums\PaymentStatus::Paid => 'heroicon-o-banknotes',
        \App\Enums\PaymentStatus::Free => 'heroicon-o-gift',
        \App\Enums\PaymentStatus::Refunded => 'heroicon-o-arrow-uturn-left',
        \App\Enums\PaymentStatus::Pending => 'heroicon-o-credit-card',
        default => 'heroicon-o-exclamation-triangle',
    };
@endphp

<div class="mt-6 grid gap-4 sm:grid-cols-2">
    @foreach([
        [__('orders.status_heading'), $order->status->label(), $statusIcon, $order->status->color()],
        [__('orders.payment_heading'), $order->payment_status->label(), $paymentIcon, $order->payment_status->color()],
    ] as [$caption, $value, $icon, $tone])
        <div class="card-warm flex items-center gap-4 p-5">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl ring-4 {{ $tones[$tone] ?? $tones['gray'] }}">
                <x-dynamic-component :component="$icon" class="h-6 w-6" aria-hidden="true" />
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ $caption }}</p>
                <p class="mt-1 font-display text-lg font-semibold text-warm-900">{{ $value }}</p>
            </div>
        </div>
    @endforeach
</div>
