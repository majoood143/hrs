{{-- A stable booking's details on its order page. $full: the customer's own (signed-in) page, with names and cancelling. --}}
@php
    $booking = $order->isStableBooking() ? $order->stableBooking : null;
    $booking?->loadMissing(['slot', 'offering', 'stable']);
    $actions = app(\App\Services\Stables\StableBookingActions::class);
    $full ??= false;
@endphp

@if($booking && $booking->slot)
    @php $start = $booking->slot->startsAt()->locale(app()->getLocale()); @endphp
    <section class="card-warm mt-6 p-6" aria-labelledby="booking-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="section-eyebrow flex items-center gap-1.5">
                    <x-heroicon-o-ticket class="h-4 w-4" aria-hidden="true" />{{ __('stable_bookings.card.eyebrow') }}
                    <span dir="ltr" class="font-mono normal-case">{{ $booking->reference }}</span>
                </p>
                <h2 id="booking-heading" class="mt-1 font-display text-xl font-semibold text-warm-900">{{ $booking->offering?->name }}</h2>
                <p class="text-sm text-warm-700">{{ $booking->stable?->name }}</p>
            </div>
            @php $tone = ['success' => 'bg-emerald-50 text-emerald-700 ring-emerald-100', 'warning' => 'bg-amber-50 text-amber-700 ring-amber-100', 'danger' => 'bg-red-50 text-red-700 ring-red-100', 'gray' => 'bg-warm-50 text-warm-700 ring-warm-100'][$booking->status->color()] ?? 'bg-warm-50 text-warm-700 ring-warm-100'; @endphp
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold ring-4 {{ $tone }}">
                <x-dynamic-component :component="match ($booking->status->value) { 'confirmed', 'completed' => 'heroicon-o-check-circle', 'pending' => 'heroicon-o-clock', 'cancelled' => 'heroicon-o-x-circle', default => 'heroicon-o-minus-circle' }" class="h-4 w-4" aria-hidden="true" />
                {{ $booking->status->label() }}
            </span>
        </div>

        <ul class="mt-4 grid gap-3 text-sm text-warm-900 sm:grid-cols-3">
            <li class="flex items-center gap-2"><x-heroicon-o-calendar class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ $start->translatedFormat('l j F Y') }}</li>
            <li class="flex items-center gap-2"><x-heroicon-o-clock class="h-5 w-5 text-warm-600" aria-hidden="true" /><span dir="ltr">{{ $booking->slot->timeRange() }}</span></li>
            <li class="flex items-center gap-2"><x-heroicon-o-user-group class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ trans_choice('stable_bookings.riders_count', $booking->riders, ['count' => $booking->riders]) }}</li>
        </ul>

        @if($full && $booking->riderNames())
            <p class="mt-3 text-sm text-warm-700">{{ $booking->riderNames() }}</p>
        @endif

        @if($booking->payment_option === 'at_stable' && $booking->isActive())
            <p class="mt-4 flex gap-2 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <x-heroicon-o-building-storefront class="h-5 w-5 shrink-0" aria-hidden="true" />
                {{ __('stable_bookings.card.pay_at_stable', ['total' => \App\Models\SiteSetting::currency()['code'].' '.number_format((float) $order->total, 3)]) }}
            </p>
        @endif

        @if($booking->payment_option === 'package' && $booking->packagePurchase)
            <p class="mt-4 flex gap-2 rounded-xl bg-warm-50 px-4 py-3 text-sm text-warm-800">
                <x-heroicon-o-rectangle-stack class="h-5 w-5 shrink-0" aria-hidden="true" />
                {{ __('stable_packages.card.used', ['name' => $booking->packagePurchase->package?->name ?? $booking->packagePurchase->name]) }}
            </p>
        @endif

        @if($booking->status === \App\Enums\StableBookingStatus::Cancelled && $booking->cancellation_source === 'stable')
            <p class="mt-4 flex gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-900">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" aria-hidden="true" />
                <span>{{ __('stable_bookings.card.cancelled_by_stable') }}@if($booking->cancellation_reason) {{ $booking->cancellation_reason }}@endif</span>
            </p>
        @endif

        <div class="mt-5 flex flex-wrap items-center gap-3">
            @if($booking->stable?->map_link && $booking->isActive())
                <a href="{{ $booking->stable->map_link }}" target="_blank" rel="noopener" class="btn-warm-outline !py-2 text-sm">
                    <x-heroicon-o-map-pin class="h-5 w-5" aria-hidden="true" />{{ __('stable_bookings.directions') }}
                </a>
            @endif

            @if($actions->customerMayCancel($booking))
                @if($full)
                    <form method="POST" action="{{ route('account.orders.cancel-booking', $order->order_number) }}"
                          onsubmit="return confirm(@js(__('stable_bookings.card.cancel_confirm')))">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            <x-heroicon-o-x-mark class="h-5 w-5" aria-hidden="true" />{{ __('stable_bookings.card.cancel') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('account.login') }}" class="text-sm font-semibold text-warm-700 underline hover:text-warm-900">{{ __('stable_bookings.card.sign_in_to_cancel') }}</a>
                @endif
            @endif
        </div>

        @if($booking->review)
            <div class="mt-5 rounded-xl border border-warm-200 p-4">
                <p class="flex items-center gap-2 text-sm font-semibold text-warm-900">{{ __('stable_reviews.yours') }} <x-stars :rating="$booking->review->rating" /></p>
                @if($booking->review->comment)<p class="mt-1 text-sm text-warm-800">{{ $booking->review->comment }}</p>@endif
            </div>
        @elseif($full && app(\App\Services\Stables\StableReviews::class)->canReview($booking, \Illuminate\Support\Facades\Auth::guard('customer')->user()))
            <form id="review" method="POST" action="{{ route('account.orders.review', $order->order_number) }}" class="mt-5 rounded-xl border border-warm-200 p-4">
                @csrf
                <fieldset>
                    <legend class="flex items-center gap-2 text-sm font-semibold text-warm-900">
                        <x-heroicon-o-star class="h-5 w-5 text-amber-500" aria-hidden="true" />{{ __('stable_reviews.ask') }}
                    </legend>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach([1, 2, 3, 4, 5] as $star)
                            <label class="cursor-pointer">
                                <input type="radio" name="rating" value="{{ $star }}" class="peer sr-only" required @checked((int) old('rating') === $star)>
                                <span class="inline-flex items-center gap-1 rounded-full border border-warm-200 px-3 py-1.5 text-sm font-semibold text-warm-800 transition peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-800 peer-focus-visible:ring-2 peer-focus-visible:ring-warm-500">
                                    {{ $star }} <x-heroicon-s-star class="h-4 w-4 text-amber-500" aria-hidden="true" />
                                    <span class="sr-only">{{ trans_choice('stable_reviews.stars', $star, ['count' => $star]) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <label for="review-comment" class="mt-3 block text-sm font-medium text-warm-900">{{ __('stable_reviews.comment') }}</label>
                <textarea id="review-comment" name="comment" rows="3" maxlength="1000" class="mt-1 w-full rounded-xl border border-warm-200 bg-white px-4 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none">{{ old('comment') }}</textarea>
                <button type="submit" class="btn-warm mt-3 !py-2 text-sm">
                    <x-heroicon-o-paper-airplane class="h-5 w-5 rtl:rotate-180" aria-hidden="true" />{{ __('stable_reviews.submit') }}
                </button>
            </form>
        @endif

        @if($booking->isActive() && $booking->status === \App\Enums\StableBookingStatus::Confirmed)
            @php $hours = $booking->stable?->bookingSettings()->cancellationHours(); @endphp
            <p class="mt-3 text-xs text-warm-700">{{ $hours === null ? __('stable_bookings.policy.no_online_cancel') : __('stable_bookings.policy.cancel_until', ['hours' => $hours]) }}</p>
        @endif
    </section>
@endif
