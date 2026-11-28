<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Models\AuctionWatch;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\SiteSetting;
use App\Services\Auctions\AuctionBidException;
use App\Services\Auctions\Bidding;
use App\Services\Auctions\LotState;
use App\Services\Auctions\Money;
use App\Services\Auctions\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The public auction pages (/auctions…): the list, an auction's catalogue, a lot with its live bid
 * board, registering to bid and placing bids. The page polls `state` for the price and the close
 * time; the server's clock is the only one that counts (`server_now`).
 */
class AuctionController extends Controller
{
    public function index(): View
    {
        $auctions = Auction::query()->online()->with('organizer')->withCount('publicLots')->orderByDesc('bidding_starts_at')->get()
            ->groupBy(fn (Auction $auction) => $auction->phase());

        return view('site.auctions.index', [
            'groups' => collect(['live', 'preview', 'ended'])->mapWithKeys(fn ($phase) => [$phase => $auctions->get($phase, collect())]),
            'seoTitle' => __('auctions.site.title').' — '.SiteSetting::siteName(),
            'seoDescription' => __('auctions.site.subtitle'),
        ]);
    }

    public function show(Request $request, string $auction): View
    {
        $auction = $this->auction($auction);
        $this->closeDue($auction);

        $lots = $auction->publicLots()->with('auction')
            ->when($request->string('gender')->toString(), fn ($q, $g) => $q->where('gender', $g))
            ->when(trim($request->string('q')->toString()), function ($q, string $term) {
                $like = '%'.$term.'%';
                $q->where(fn ($w) => $w->where('en_name', 'like', $like)->orWhere('ar_name', 'like', $like)
                    ->orWhere('sire_en', 'like', $like)->orWhere('dam_en', 'like', $like)->orWhere('sire_ar', 'like', $like)->orWhere('dam_ar', 'like', $like));
            })
            ->get();

        return view('site.auctions.show', [
            'auction' => $auction,
            'lots' => $lots,
            'bidder' => $this->bidder($auction),
            'filters' => array_filter($request->only(['gender', 'q'])),
            'seoTitle' => $auction->title.' — '.SiteSetting::siteName(),
            'seoDescription' => $auction->description,
            'seoImage' => $auction->cover_url,
        ]);
    }

    public function lot(string $auction, int $lot): View
    {
        $auction = $this->auction($auction);
        $this->closeDue($auction);
        $lot = $this->lotOf($auction, $lot);
        $bidder = $this->bidder($auction);

        return view('site.auctions.lot', [
            'auction' => $auction,
            'lot' => $lot,
            'bidder' => $bidder,
            'state' => $this->stateOf($lot, $bidder),
            'watching' => ($customer = $this->customer()) && $lot->watches()->where('customer_id', $customer->id)->exists(),
            'previous' => $auction->publicLots()->where('lot_number', '<', $lot->lot_number)->orderByDesc('lot_number')->first(),
            'next' => $auction->publicLots()->where('lot_number', '>', $lot->lot_number)->first(),
            'suggestions' => $auction->publicLots()->with('auction')->whereKeyNot($lot->id)->inRandomOrder()->limit(3)->get(),
            'seoTitle' => __('auctions.site.lot_title', ['number' => $lot->lot_number, 'name' => $lot->name]).' — '.$auction->title,
            'seoDescription' => $lot->tagline,
            'seoImage' => $lot->photoUrls()[0] ?? $auction->cover_url,
        ]);
    }

    public function state(string $auction, int $lot): JsonResponse
    {
        $auction = $this->auction($auction);
        $this->closeDue($auction);
        $lot = $this->lotOf($auction, $lot);

        return response()->json($this->stateOf($lot, $this->bidder($auction)));
    }

    /** Every lot's price and close time, for the catalogue page. */
    public function lotsState(string $auction): JsonResponse
    {
        $auction = $this->auction($auction);
        $this->closeDue($auction);

        return response()->json([
            'server_now' => now()->toIso8601String(),
            'lots' => $auction->publicLots()->with('auction')->get()->map(fn (AuctionLot $lot) => [
                'number' => $lot->lot_number,
                'status' => $lot->status,
                'price' => $this->priceLabel($lot),
                'ends_at' => $lot->ends_at?->toIso8601String(),
                'bids' => $lot->bids_count,
                'bidders' => $lot->bidders_count,
            ])->values(),
        ]);
    }

    public function bid(Request $request, string $auction, int $lot, Bidding $bidding): JsonResponse|RedirectResponse
    {
        $auction = $this->auction($auction);
        $lot = $this->lotOf($auction, $lot);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.001', 'max:9999999'],
            'max_amount' => ['nullable', 'numeric', 'min:0.001', 'max:9999999'],
        ]);

        $bidder = $this->bidder($auction);

        try {
            if (! $bidder) {
                throw new AuctionBidException('not_registered');
            }

            $lot = $bidding->place($lot, $bidder, Money::baisa($data['amount']), filled($data['max_amount'] ?? null) ? Money::baisa($data['max_amount']) : null);
            AuctionWatch::query()->firstOrCreate(['customer_id' => $bidder->customer_id, 'auction_lot_id' => $lot->id]);
        } catch (AuctionBidException $e) {
            $state = $this->stateOf($lot->fresh(), $bidder);

            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'reason' => $e->reason, 'errors' => ['amount' => [$e->getMessage()]], 'state' => $state], 422)
                : back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        $state = $this->stateOf($lot->fresh(), $bidder);
        $message = $state['you'] === 'leading' ? __('auctions.site.bid_leading') : __('auctions.site.bid_outbid_at_once');

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'state' => $state])
            : back()->with('status', $message);
    }

    public function watch(Request $request, string $auction, int $lot): JsonResponse|RedirectResponse
    {
        $lot = $this->lotOf($this->auction($auction), $lot);
        $watch = AuctionWatch::query()->where('customer_id', $this->customer()->id)->where('auction_lot_id', $lot->id)->first();

        $watch ? $watch->delete() : AuctionWatch::query()->create(['customer_id' => $this->customer()->id, 'auction_lot_id' => $lot->id]);

        return $request->expectsJson() ? response()->json(['watching' => ! $watch]) : back();
    }

    public function registerForm(string $auction, Registration $registration): View|RedirectResponse
    {
        $auction = $this->auction($auction);
        $bidder = $this->bidder($auction);

        if ($bidder?->status === 'approved') {
            return redirect()->route('auctions.show', $auction->slug);
        }

        return view('site.auctions.register', [
            'auction' => $auction,
            'bidder' => $bidder,
            'customer' => $this->customer(),
            'payable' => $bidder ? $registration->payableDeposit($bidder) : null,
            'seoTitle' => __('auctions.site.register_title').' — '.$auction->title,
            'noindex' => true,
        ]);
    }

    public function register(Request $request, string $auction, Registration $registration): RedirectResponse
    {
        $auction = $this->auction($auction);
        $customer = $this->customer();

        $data = $request->validate([
            'name' => [$customer->name ? 'nullable' : 'required', 'string', 'max:255'],
            'national_id' => [$customer->national_id ? 'nullable' : 'required', 'string', 'regex:/^[A-Za-z0-9]{4,20}$/'],
            'accept' => ['accepted'],
        ]);

        $customer->forceFill(array_filter(['name' => $data['name'] ?? null, 'national_id' => $data['national_id'] ?? null]))->save();

        try {
            $bidder = $registration->register($auction, $customer);
        } catch (AuctionBidException $e) {
            return back()->withErrors(['accept' => $e->getMessage()]);
        }

        if ($order = $registration->payableDeposit($bidder)) {
            return redirect()->route('payment.start', $order->order_number);
        }

        return redirect()->route('auctions.show', $auction->slug)->with('status', $bidder->status === 'approved'
            ? __('auctions.site.registered_approved')
            : __('auctions.site.registered_pending'));
    }

    /** Vet reports and X-rays: approved bidders of the auction only. */
    public function document(string $auction, int $lot, int $index): StreamedResponse
    {
        $auction = $this->auction($auction);
        $lot = $this->lotOf($auction, $lot);
        abort_unless($this->bidder($auction)?->status === 'approved', 403);

        $path = ($lot->documents ?? [])[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, basename($path));
    }

    /**
     * What the bid board shows. Never the reserve amount or anyone else's maximum.
     *
     * @return array<string, mixed>
     */
    private function stateOf(AuctionLot $lot, ?AuctionBidder $bidder): array
    {
        $lot->loadMissing(['auction', 'leadingBid']);
        $open = $lot->isOpenAt(now());
        $minimum = $lot->minimumNextBid();
        $zone = BookingSlot::timezone();

        $you = LotState::you($lot, $bidder);

        return [
            'status' => $lot->status,
            'open' => $open,
            'not_started' => $lot->status === 'ready' && ! $open && $lot->ends_at?->isFuture(),
            'price' => $this->priceLabel($lot),
            'starting' => SiteSetting::formatCurrency($lot->starting_price),
            'minimum' => Money::omr($minimum),
            'minimum_label' => SiteSetting::formatCurrency(Money::omr($minimum)),
            'step' => Money::omr($lot->auction->steps()->after($lot->current_price === null ? Money::baisa($lot->starting_price) : Money::baisa($lot->current_price))),
            'ends_at' => $lot->ends_at?->toIso8601String(),
            'server_now' => now()->toIso8601String(),
            'bids' => $lot->bids_count,
            'bidders' => $lot->bidders_count,
            'reserve' => LotState::reserve($lot),
            'you' => $you,
            'invoice' => $you === 'won' && ($invoice = $lot->invoice) ? [
                'pay_url' => $invoice->isPayable() ? route('payment.start', $invoice->order_number) : null,
                'paid' => $invoice->isPaid(),
                'total' => SiteSetting::formatCurrency($invoice->total),
                'due' => $lot->payment_due_at?->timezone($zone)->translatedFormat('j M Y, H:i'),
                'order_url' => route('account.orders.show', $invoice->order_number),
            ] : null,
            'your_max' => $you === 'leading' ? SiteSetting::formatCurrency($lot->leadingBid->max_amount) : null,
            'history' => $lot->bids()->visible()->with('bidder')->latest('id')->limit(10)->get()->map(fn (AuctionBid $bid) => [
                'bidder' => $bid->bidder?->label(),
                'mine' => $bidder !== null && (int) $bid->auction_bidder_id === (int) $bidder->id,
                'amount' => SiteSetting::formatCurrency($bid->amount),
                'at' => $bid->created_at->timezone($zone)->format('H:i:s'),
            ])->values(),
        ];
    }

    private function priceLabel(AuctionLot $lot): ?string
    {
        if (LotState::priceHidden($lot)) {
            return __('auctions.site.price_on_request');
        }

        $amount = LotState::publicPrice($lot);

        return $amount === null ? null : SiteSetting::formatCurrency($amount);
    }

    private function closeDue(Auction $auction): void
    {
        LotState::closeDue($auction);
    }

    private function auction(string $slug): Auction
    {
        return Auction::query()->online()->with('organizer')->where('slug', $slug)->firstOrFail();
    }

    private function lotOf(Auction $auction, int $number): AuctionLot
    {
        $lot = $auction->publicLots()->where('lot_number', $number)->firstOrFail();
        $lot->setRelation('auction', $auction);

        return $lot;
    }

    private function bidder(Auction $auction): ?AuctionBidder
    {
        $customer = $this->customer();

        return $customer ? AuctionBidder::query()->where('auction_id', $auction->id)->where('customer_id', $customer->id)->first() : null;
    }

    private function customer(): ?Customer
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        return $customer;
    }
}
