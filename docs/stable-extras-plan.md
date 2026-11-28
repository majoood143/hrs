# Stable extras (add-ons to a booked session) — plan

Customers add extras (photos, helmet hire, transport…) to a stable session. Modelled on the booking project's
`extra_services` (per event, admin only, stock counter), changed for stables: owners run their own extras,
admins keep the switches, insights for both.

## Decisions (2026-11-28)

- **Extras are part of the order's price** (`price = session × riders + extras`), like promo codes: fee, VAT by
  supplier, commission, refunds, statements, payouts, POS and the expiry sweep need nothing new.
- Commission applies to extras (it is on the price).
- A promo code's discount comes off the whole price, extras included.
- No extras on a booking paid with a lesson package (v1): that booking's order is free.
- No stock in v1; if needed later, count live from the bookings (never a stored counter).

## Steps

1. **Data + control**: `stable_extras` (stable, en/ar name + description, photo, `price decimal(10,3)`,
   `pricing` per_booking | per_rider, `max_quantity`, `is_active`, `sort`, soft deletes, optional
   `stable_extra_offering` pivot: none = every service) and `stable_booking_extras` (snapshot: names, unit price,
   quantity, amount). General Settings → Modules → Extra services (`module_extras_enabled`, off by default,
   `StableExtra::enabled()`), each stable's switch `stables.extras_enabled` (admin → the stable). Stable panel →
   Extras (`StableExtraResource`), admin → Extras (all stables).
2. **Booking flow**: `StableBookingPricing::quote(..., $extrasBaisa)`, `CreateStableBooking::handle(..., $extras)`
   (checked inside the locked transaction: this stable's, active, for this service, quantity ≤ max), the
   website form, receipt / ticket / emails / admin order lines.
3. **API + app**: `extras` on `GET bookings/slots/{id}` (+ `?extras[id]=qty` re-quotes), `POST bookings/slots/{id}`,
   the booking resources; app booking form card, `QuoteRows` line, booking details (customer + owner).
4. **Desk (POS)**: `pos/quote` and `pos/sales` take `extras`; app POS screen.
5. **Insights**: `InsightsExtras` (revenue, attach rate, top extras) on the stable Insights and admin
   `StableInsights` (+ top stables by extras), extras in `PosReport` "by item", an Extras column on the owner's
   bookings, an extras total in the app's Income.

Tests: `tests/Feature/Api/V1/StableExtrasTest.php`.

## Progress

- [x] 1 · [x] 2 · [x] 3 · [x] 4 · [x] 5 (2026-11-28)
- Not done: extras as their own rows in `PosReport` "by item" and an extras total in the app's Income screen
  (extras revenue is on both Insights pages); refunds of a single extra (a partial refund of the order works).
- Not yet seen: the web build or a native build of the app screens.
