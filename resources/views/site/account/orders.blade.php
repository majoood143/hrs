<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-4xl px-6 py-14">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="section-eyebrow">{{ __('account.eyebrow') }}</p>
                <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900">{{ __('account.orders_heading') }}</h1>
                <p class="mt-1 text-sm text-warm-700" dir="ltr">{{ $customer->displayName() }}</p>
            </div>
            <form method="POST" action="{{ route('account.logout') }}">
                @csrf
                <button type="submit" class="btn-warm-outline">{{ __('account.logout') }}</button>
            </form>
        </div>

        @if($orders->isEmpty())
            <p class="card-warm mt-8 p-6 text-sm text-warm-700">{{ __('account.no_orders') }}</p>
        @else
            <ul class="mt-8 space-y-3">
                @foreach($orders as $order)
                    <li>
                        <a href="{{ route('account.orders.show', $order->order_number) }}" class="card-warm flex flex-wrap items-center justify-between gap-3 p-5 transition hover:shadow-md">
                            <div>
                                <p class="font-display text-lg font-semibold text-warm-900" dir="ltr">{{ $order->order_number }}</p>
                                <p class="text-sm text-warm-700">{{ $order->service?->localizedName() ?? __('orders.service') }} · <span dir="ltr">{{ $order->created_at->format('Y-m-d') }}</span></p>
                            </div>
                            <div class="text-end">
                                <p class="font-semibold text-warm-900" dir="ltr">{{ \App\Models\SiteSetting::currency()['code'] }} {{ number_format((float) $order->total, 3) }}</p>
                                <p class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ $order->status->label() }} · {{ $order->payment_status->label() }}</p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </div>
</x-layouts.site>
