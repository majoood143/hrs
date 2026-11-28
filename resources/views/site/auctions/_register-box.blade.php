{{-- Where the visitor stands with this auction, and the one thing to do next. --}}
@php $status = $bidder?->status; @endphp
<div class="card-warm flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $status === 'approved' ? 'bg-emerald-50 text-emerald-600' : 'bg-warm-50 text-warm-600' }}">
            <x-dynamic-component :component="$status === 'approved' ? 'heroicon-o-check-badge' : 'heroicon-o-identification'" class="h-6 w-6" aria-hidden="true" />
        </span>
        <div>
            <p class="font-semibold text-warm-900">{{ __('auctions.site.register_states.'.($status ?? 'none')) }}</p>
            <p class="text-sm text-warm-700">
                @if($status === 'approved')
                    {{ __('auctions.site.your_number', ['number' => $bidder->paddle_number]) }}
                @elseif(! $status && (float) $auction->deposit_amount > 0)
                    {{ __('auctions.site.deposit_note', ['amount' => \App\Models\SiteSetting::formatCurrency($auction->deposit_amount)]) }}
                @else
                    {{ __('auctions.site.register_help.'.($status ?? 'none')) }}
                @endif
            </p>
        </div>
    </div>
    @if(! in_array($status, ['approved', 'rejected', 'blocked'], true) && $auction->phase() !== 'ended')
        <a href="{{ route('auctions.register', $auction->slug) }}" class="btn-warm shrink-0">
            <x-heroicon-o-hand-raised class="h-5 w-5" aria-hidden="true" />{{ $status ? __('auctions.site.continue_registration') : __('auctions.site.register_to_bid') }}
        </a>
    @endif
</div>
