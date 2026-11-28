<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Api\V1\Owner\Concerns\FindsOwnerStable;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Bookings\ExtraLineResource;
use App\Http\Resources\V1\Bookings\ExtraResource;
use App\Http\Resources\V1\MoneyResource;
use App\Http\Resources\V1\OptionResource;
use App\Http\Resources\V1\Owner\PosSaleResource;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableExtra;
use App\Models\StablePackage;
use App\Models\User;
use App\Services\Reports\PosReport;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\DeskSales;
use App\Support\PhoneNumber;
use App\Support\StablePos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The stable's desk (POS) in the app: sell a session or a lesson package to someone at the desk, or
 * book for a customer who cannot book themselves, take the payment (split across methods, or send a
 * payment link), refund, and the desk's sales report. Behind General Settings → Modules → POS, the
 * stable's own switch (admin) and the `stable_pos` app feature. Times come from `GET …/slots`.
 */
class OwnerPosController extends Controller
{
    use FindsOwnerStable;

    public const PERIODS = ['today', 'this_week', 'this_month', 'last_month', 'this_year'];

    public function __construct(private readonly DeskSales $sales) {}

    /**
     * The desk
     *
     * What the desk can sell (the stable's active lesson packages; times come from `slots`) and the
     * payment methods it takes.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $stable = $this->deskStable($request, $slug);

        return response()->json([
            'methods' => collect(StablePos::methodsFor($stable))->map(fn (string $m) => OptionResource::of($m, StablePos::label($m)))->all(),
            'packages' => $stable->packages()->active()->with('offering')->orderBy('price')->get()
                ->filter(fn (StablePackage $p) => $p->offering?->is_active)
                ->map(fn (StablePackage $p) => [
                    'id' => (int) $p->id,
                    'name' => $p->name,
                    'service' => (string) $p->offering->name,
                    'sessions' => (int) $p->sessions,
                    'validity_days' => (int) $p->validity_days,
                    'price' => new MoneyResource($p->price),
                ])->values()->all(),
        ]);
    }

    /**
     * Price a desk sale
     *
     * The total of a session (a time and a number of riders, plus `extras`) or a package, with a promo code if one
     * is typed: no service fee at the desk. 409 `booking_unavailable` with the reason when it cannot
     * be sold (a full time, an unusable code…).
     */
    public function quote(Request $request, string $slug): JsonResponse
    {
        $stable = $this->deskStable($request, $slug);
        $data = $request->validate($this->itemRules() + [
            'riders' => ['nullable', 'integer', 'min:1', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);
        [$slot, $package] = $this->item($stable, $data);

        try {
            if ($slot) {
                app(CreateStableBooking::class)->assertBookable($slot, (int) ($data['riders'] ?? 1), atDesk: true);
            }
            $quote = $this->sales->quote($stable, $slot, $package, (int) ($data['riders'] ?? 1), $data['promo_code'] ?? null, $data['phone'] ?? null, $data['extras'] ?? []);
        } catch (BookingUnavailable $e) {
            return $this->unavailable($e);
        }

        $money = fn (int $baisa) => new MoneyResource($baisa / 1000);
        $lines = $slot ? StableExtra::lines($slot->offering, (int) ($data['riders'] ?? 1), $data['extras'] ?? []) : [];

        return response()->json([
            /** What can be added to this session (`extras[<id>]=<quantity>`); empty for a package. */
            'extras' => $slot ? ExtraResource::collection(StableExtra::forOffering($slot->offering)) : [],
            /** The extras chosen; their total is in `price`. */
            'extra_lines' => ExtraLineResource::collection($lines),
            'price' => $money($quote->price + $quote->discount),
            /** @var MoneyResource|null */
            'discount' => $quote->discount > 0 ? $money($quote->discount) : null,
            'vat' => $money($quote->vatOnPrice),
            'total' => $money($quote->total()),
            /** Our commission, out of the stable's share (not added to the total). */
            'commission' => $money($quote->commission + $quote->vatOnCommission),
        ]);
    }

    /**
     * A customer at the desk
     *
     * The name and email of the customer with this phone, when they have booked at this stable
     * before (nobody else's details are shown). Their bookings come with them when they sign in.
     */
    public function customer(Request $request, string $slug): JsonResponse
    {
        $stable = $this->deskStable($request, $slug);
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = PhoneNumber::normalize($data['phone']);
        $known = $phone && ServiceOrder::query()->where('stable_id', $stable->getKey())->where('customer_phone', $phone)->exists();
        $customer = $known ? Customer::query()->where('phone', $phone)->first() : null;
        $last = $known ? ServiceOrder::query()->where('stable_id', $stable->getKey())->where('customer_phone', $phone)->latest('id')->first() : null;

        return response()->json([
            'known' => $known,
            /** @var string|null */
            'name' => $customer?->name ?: $last?->customer_name,
            /** @var string|null */
            'email' => $customer?->email ?: $last?->customer_email,
        ]);
    }

    /**
     * Make a desk sale
     *
     * A session (`slot_id`, the riders' names) or a package (`package_id`, needs the phone), for the
     * customer named. `payments` (one per method, adding up to the total exactly) = paid at the desk
     * and confirmed at once; `link` true instead = left unpaid with a payment link (`pay_url`) to send
     * the customer. 409 `booking_unavailable` with the reason when it cannot be sold.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $stable = $this->deskStable($request, $slug);
        $data = $request->validate($this->itemRules() + [
            'riders' => ['required_with:slot_id', 'array', 'min:1', 'max:20'],
            'riders.*' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'required_with:package_id', 'required_if:link,true', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'link' => ['nullable', 'boolean'],
            'payments' => ['nullable', 'array', 'max:5'],
            'payments.*.method' => ['required', 'string', Rule::in(StablePos::METHODS)],
            'payments.*.amount' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);
        [$slot, $package] = $this->item($stable, $data);
        /** @var User $user */
        $user = $request->user('owner_api');
        $payments = ($data['link'] ?? false) ? null : ($data['payments'] ?? []);
        $customer = ['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'email' => $data['email'] ?? null];

        if ($customer['phone'] !== null && ! PhoneNumber::normalize($customer['phone'])) {
            return response()->json(['message' => __('stable_pos.errors.phone'), 'errors' => ['phone' => [__('stable_pos.errors.phone')]]], 422);
        }

        try {
            $order = $slot
                ? $this->sales->sellSession($stable, $slot, array_values($data['riders']), $customer, $payments, $data['promo_code'] ?? null, $user, $data['extras'] ?? [])->order
                : $this->sales->sellPackage($stable, $package, $customer, $payments, $data['promo_code'] ?? null, $user)->order;
        } catch (BookingUnavailable $e) {
            return $this->unavailable($e);
        }

        return PosSaleResource::make(PosReport::deskOrders($stable)->findOrFail($order->getKey()))->response()->setStatusCode(201);
    }

    /**
     * The desk's sales
     *
     * A period's desk sales (newest first, at most 200) and the report: totals, by payment method,
     * by service or package, by who sold, by day; payment links still unpaid.
     */
    public function sales(Request $request, string $slug): JsonResponse
    {
        $stable = $this->deskStable($request, $slug);
        $data = $request->validate([
            /** Defaults to today. */
            'period' => ['nullable', Rule::in(self::PERIODS)],
        ]);
        $period = $data['period'] ?? 'today';
        $report = PosReport::forPeriod($stable, $period);
        $t = $report->totals();
        $money = fn (int $baisa) => new MoneyResource($baisa / 1000);

        return response()->json([
            'period' => [
                'value' => $period,
                'label' => __('admin_income_report.periods.'.$period),
                /** @example 2026-11-30 */
                'from' => $report->from->toDateString(),
                /** @example 2026-11-30 */
                'to' => $report->to->toDateString(),
            ],
            'periods' => collect(self::PERIODS)->map(fn (string $p) => OptionResource::of($p, __('admin_income_report.periods.'.$p)))->all(),
            'totals' => [
                'count' => $t['count'],
                'sales' => $money($t['sales']),
                'refunds' => $money($t['refunds']),
                /** sales − refunds */
                'net' => $money($t['net']),
                /** Our commission (and its VAT) on them, owed by the stable in its statement. */
                'commission' => $money($t['commission']),
                'stable_share' => $money($t['stable_share']),
                /** The average sale. */
                'average' => $money($t['count'] > 0 ? intdiv($t['sales'], $t['count']) : 0),
                'unpaid_links' => $money($t['unpaid_links']),
                'unpaid_links_count' => $t['unpaid_links_count'],
            ],
            'by_method' => collect($report->byMethod())->map(fn (array $r) => [
                'method' => OptionResource::of($r['method'], StablePos::label($r['method'])),
                'total' => $money($r['total']),
                'count' => $r['count'],
            ])->all(),
            'by_item' => collect($report->byItem())->map(fn (array $r) => ['item' => $r['item'], 'total' => $money($r['total']), 'count' => $r['count']])->all(),
            'by_seller' => collect($report->bySeller())->map(fn (array $r) => ['name' => $r['name'], 'total' => $money($r['total']), 'count' => $r['count']])->all(),
            'by_day' => collect($report->byDay())->map(fn (array $r) => ['date' => $r['date'], 'total' => $money($r['total']), 'count' => $r['count']])->all(),
            'data' => PosSaleResource::collection($report->orders()->take(200)),
        ]);
    }

    /**
     * Refund a desk sale
     *
     * Gives the money back in full (the stable hands it over); a session still to come is cancelled
     * and the customer is sent the reason.
     */
    public function refund(Request $request, string $slug, string $number): PosSaleResource|JsonResponse
    {
        $stable = $this->deskStable($request, $slug);
        $data = $request->validate([
            /** Sent to the customer. */
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $order = PosReport::deskOrders($stable)->where('order_number', $number)->firstOrFail();
        /** @var User $user */
        $user = $request->user('owner_api');

        try {
            $this->sales->refund($order, $data['reason'], $user);
        } catch (BookingUnavailable $e) {
            return $this->unavailable($e);
        }

        return PosSaleResource::make(PosReport::deskOrders($stable)->findOrFail($order->getKey()));
    }

    /** The owner's stable, when its desk is open (module, the stable's switch, taking bookings). */
    private function deskStable(Request $request, string $slug): Stable
    {
        $stable = $this->ownerStable($request, $slug);

        abort_unless(StablePos::openFor($stable), response()->json(['message' => __('stable_pos.errors.closed'), 'code' => 'pos_closed'], 403));

        return $stable;
    }

    /** @return array<string, list<mixed>> */
    private function itemRules(): array
    {
        return [
            'slot_id' => ['nullable', 'required_without:package_id', 'prohibits:package_id', 'integer'],
            'package_id' => ['nullable', 'required_without:slot_id', 'integer'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            /** extra id => quantity, with a session only */
            'extras' => ['nullable', 'array', 'max:30'],
            'extras.*' => ['integer', 'min:0', 'max:20'],
        ];
    }

    /** @return array{0: ?BookingSlot, 1: ?StablePackage} the time or the package, among the stable's own */
    private function item(Stable $stable, array $data): array
    {
        if (! empty($data['slot_id'])) {
            return [BookingSlot::query()->where('stable_id', $stable->getKey())->with(['offering', 'stable'])->findOrFail($data['slot_id']), null];
        }

        return [null, $stable->packages()->active()->with(['offering', 'stable'])->findOrFail($data['package_id'])];
    }

    private function unavailable(BookingUnavailable $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage(), 'code' => 'booking_unavailable'], 409);
    }
}
