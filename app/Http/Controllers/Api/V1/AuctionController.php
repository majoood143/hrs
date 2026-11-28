<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Auctions\AuctionResource;
use App\Http\Resources\V1\Auctions\LotListResource;
use App\Http\Resources\V1\Auctions\LotResource;
use App\Http\Resources\V1\Auctions\LotStateResource;
use App\Http\Resources\V1\MoneyResource;
use App\Models\AppEvent;
use App\Models\Auction;
use App\Models\AuctionApplication;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Models\AuctionWatch;
use App\Models\Customer;
use App\Services\Auctions\AuctionBidException;
use App\Services\Auctions\Bidding;
use App\Services\Auctions\LotState;
use App\Services\Auctions\Money;
use App\Services\Auctions\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Online horse auctions (the website's /auctions): reading is open, registering, bidding and the
 * watchlist need a signed-in customer and the `auctions` feature. The rules are the website's
 * (Services\Auctions\*); lots are addressed by their number in the auction.
 */
class AuctionController extends Controller
{
    use PaginatesListings;

    /**
     * List auctions
     *
     * Published auctions whose catalogue is online, newest bidding first. Group them by `phase`.
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1']]);

        return AuctionResource::collection(
            Auction::query()->online()->with('organizer')->withCount('publicLots')->orderByDesc('bidding_starts_at')
                ->paginate($this->perPage($request))->withQueryString()
        );
    }

    /**
     * Show an auction
     *
     * With its lots, its conditions and, when a customer's token is sent, their registration (`me`).
     *
     * @unauthenticated
     */
    public function show(Request $request, string $slug): AuctionResource
    {
        $auction = $this->auction($slug);
        LotState::closeDue($auction);

        return AuctionResource::detail($auction, $this->bidder($request, $auction));
    }

    /**
     * Show a lot
     *
     * The horse, its pedigree, the vet documents (approved bidders), and the bid board (`state`).
     *
     * @unauthenticated
     */
    public function lot(Request $request, string $slug, int $number): LotResource
    {
        $auction = $this->auction($slug);
        LotState::closeDue($auction);
        $lot = $this->lotOf($auction, $number);
        $customer = $this->customer($request);

        return new LotResource($lot, $this->bidder($request, $auction), $customer !== null && $lot->watches()->where('customer_id', $customer->id)->exists());
    }

    /**
     * A lot's bid board
     *
     * Poll every 3 s while the lot is on screen.
     *
     * @unauthenticated
     */
    public function state(Request $request, string $slug, int $number): LotStateResource
    {
        $auction = $this->auction($slug);
        LotState::closeDue($auction);

        return new LotStateResource($this->lotOf($auction, $number), $this->bidder($request, $auction));
    }

    /**
     * Every lot's price and close time
     *
     * For the catalogue; poll every 15 s while it is on screen.
     *
     * @unauthenticated
     */
    public function lotsState(string $slug): JsonResponse
    {
        $auction = $this->auction($slug);
        LotState::closeDue($auction);

        return response()->json([
            'server_now' => now()->toIso8601String(),
            'lots' => LotListResource::collection($auction->publicLots()->with('auction')->get()),
        ]);
    }

    /**
     * Register to bid
     *
     * Accepts the auction's conditions. With a deposit, `deposit_pay_url` is its checkout (open it in
     * the payment sheet); approval follows the payment or an admin. A 422 with `code` explains a refusal.
     */
    public function register(Request $request, string $slug, Registration $registration): JsonResponse
    {
        $auction = $this->auction($slug);
        $customer = $this->customer($request);

        $data = $request->validate([
            'name' => [$customer->name ? 'nullable' : 'required', 'string', 'max:255'],
            /** Kept encrypted; asked once per customer. */
            'national_id' => [$customer->national_id ? 'nullable' : 'required', 'string', 'regex:/^[A-Za-z0-9]{4,20}$/'],
            'accept' => ['accepted'],
        ]);
        $customer->forceFill(array_filter(['name' => $data['name'] ?? null, 'national_id' => $data['national_id'] ?? null]))->save();

        try {
            $bidder = $registration->register($auction, $customer);
        } catch (AuctionBidException $e) {
            return $this->refused($e);
        }

        AppEvent::record('auction_registered', $customer, ['auction' => $auction->slug]);
        $order = $registration->payableDeposit($bidder);

        return response()->json(['data' => [
            'status' => $bidder->status,
            'paddle_number' => $bidder->paddle_number,
            'deposit_pay_url' => $order ? route('payment.start', $order->order_number) : null,
        ]]);
    }

    /**
     * Place a bid
     *
     * `amount` is placed now; `max_amount` (optional) lets the server bid for them up to it. Answers
     * the new bid board. A 422 has `code` (too_low, closed, not_registered…) and the board as it is now.
     */
    public function bid(Request $request, string $slug, int $number, Bidding $bidding): JsonResponse
    {
        $auction = $this->auction($slug);
        $lot = $this->lotOf($auction, $number);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.001', 'max:9999999'],
            'max_amount' => ['nullable', 'numeric', 'min:0.001', 'max:9999999'],
        ]);
        $bidder = $this->bidder($request, $auction);

        try {
            if (! $bidder) {
                throw new AuctionBidException('not_registered');
            }
            $lot = $bidding->place($lot, $bidder, Money::baisa($data['amount']), filled($data['max_amount'] ?? null) ? Money::baisa($data['max_amount']) : null);
        } catch (AuctionBidException $e) {
            return $this->refused($e, new LotStateResource($lot->fresh(), $bidder));
        }

        AuctionWatch::query()->firstOrCreate(['customer_id' => $bidder->customer_id, 'auction_lot_id' => $lot->id]);
        AppEvent::record('bid_placed', $this->customer($request), ['auction' => $auction->slug, 'lot' => $lot->lot_number, 'amount' => (string) $data['amount']]);
        $state = new LotStateResource($lot->fresh(), $bidder);
        $you = $state->toArray($request)['you'];

        return response()->json([
            'message' => $you === 'leading' ? __('auctions.site.bid_leading') : __('auctions.site.bid_outbid_at_once'),
            'data' => $state,
        ]);
    }

    /** Watch a lot (they are told 15 minutes before it closes). */
    public function watch(Request $request, string $slug, int $number): JsonResponse
    {
        $lot = $this->lotOf($this->auction($slug), $number);
        AuctionWatch::query()->firstOrCreate(['customer_id' => $this->customer($request)->id, 'auction_lot_id' => $lot->id]);

        return response()->json(['data' => ['watching' => true]]);
    }

    /** Stop watching a lot. */
    public function unwatch(Request $request, string $slug, int $number): JsonResponse
    {
        $lot = $this->lotOf($this->auction($slug), $number);
        AuctionWatch::query()->where('customer_id', $this->customer($request)->id)->where('auction_lot_id', $lot->id)->delete();

        return response()->json(['data' => ['watching' => false]]);
    }

    /**
     * A vet document
     *
     * Only through the signed link in the lot (`documents[].url`), which lasts 30 minutes.
     *
     * @unauthenticated
     */
    public function document(string $slug, int $number, int $index): StreamedResponse
    {
        $lot = $this->lotOf($this->auction($slug), $number);
        $path = ($lot->documents ?? [])[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, basename($path));
    }

    /**
     * My auctions
     *
     * The lots they bid on (with their result and the winner's checkout), their watchlist,
     * registrations, and the horses they sell (applications and lots).
     */
    public function account(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $bidderIds = AuctionBidder::query()->where('customer_id', $customer->id)->pluck('id');

        $bids = AuctionLot::query()->with(['auction', 'leadingBid', 'invoice'])
            ->whereHas('bids', fn ($q) => $q->valid()->whereIn('auction_bidder_id', $bidderIds))
            ->orderByDesc('ends_at')->get()
            ->map(function (AuctionLot $lot) use ($bidderIds, $request) {
                $bidder = AuctionBidder::query()->whereIn('id', $bidderIds)->where('auction_id', $lot->auction_id)->first();
                $you = LotState::you($lot, $bidder);

                return (new LotListResource($lot))->toArray($request) + [
                    'you' => $you,
                    'pay_url' => $you === 'won' && $lot->invoice?->isPayable() ? route('payment.start', $lot->invoice->order_number) : null,
                ];
            });

        return response()->json(['data' => [
            'bids' => $bids,
            'watchlist' => LotListResource::collection(AuctionLot::query()->with('auction')->where('status', 'ready')
                ->whereHas('watches', fn ($q) => $q->where('customer_id', $customer->id))
                ->whereNotIn('id', $bids->pluck('id'))->orderBy('ends_at')->get()),
            'registrations' => AuctionBidder::query()->with('auction')->where('customer_id', $customer->id)
                ->whereHas('auction', fn ($q) => $q->where('status', 'published'))->latest('id')->get()
                ->map(fn (AuctionBidder $b) => [
                    'auction_slug' => $b->auction->slug,
                    'auction_title' => $b->auction->title,
                    'status' => $b->status,
                    'paddle_number' => $b->paddle_number,
                ]),
            'selling' => AuctionLot::query()->with(['auction', 'invoice'])->where('seller_customer_id', $customer->id)
                ->where('status', '!=', 'draft')->whereHas('auction', fn ($q) => $q->where('status', 'published'))
                ->orderByDesc('ends_at')->get()
                ->map(fn (AuctionLot $lot) => (new LotListResource($lot))->toArray($request) + [
                    /** live, sold, buyer_paid, paid_out, unsold, withdrawn */
                    'result' => match ($lot->status) {
                        'sold' => $lot->payout_paid_at ? 'paid_out' : ($lot->invoice?->isPaid() ? 'buyer_paid' : 'sold'),
                        'unsold', 'withdrawn' => $lot->status,
                        default => 'live',
                    },
                    'payout' => $lot->payout_amount === null ? null : new MoneyResource($lot->payout_amount),
                ]),
            'applications' => AuctionApplication::query()->where('customer_id', $customer->id)->where('status', '!=', 'approved')->latest('id')->get()
                ->map(fn (AuctionApplication $a) => [
                    'id' => $a->id,
                    'horse_name' => $a->horse_name,
                    /** pending, rejected */
                    'status' => $a->status,
                    'reject_reason' => $a->reject_reason,
                    'sent_at' => $a->created_at->toIso8601String(),
                ]),
            /** The website's form: sellers apply there. */
            'sell_url' => route('auctions.sell'),
        ]]);
    }

    private function refused(AuctionBidException $e, ?LotStateResource $state = null): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $e->getMessage(),
            'code' => $e->reason,
            'errors' => ['amount' => [$e->getMessage()]],
            'data' => $state,
        ]), 422);
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

    private function bidder(Request $request, Auction $auction): ?AuctionBidder
    {
        $customer = $this->customer($request);

        return $customer ? AuctionBidder::query()->where('auction_id', $auction->id)->where('customer_id', $customer->id)->first() : null;
    }

    private function customer(Request $request): ?Customer
    {
        return $request->user('customer_api');
    }
}
