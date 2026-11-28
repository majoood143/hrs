# Online horse auctions: plan

Status: **Phases 1–5 built, phase 6 pilot test done** (2026-11-24); the real pilot sale follows `docs/auction-pilot-runbook.md`. Progress is in §11. Benchmark: HORSE24 (horse24.com).
Website first (`../hrs`), then `/api/v1` and the app (`../hrs-mobile`), like every other feature.

## 1. What HORSE24 does (and what we take from it)

Read from its public pages via search ([How to bid](https://horse24.com/en/how-to-bid), [FAQ](https://horse24.com/en/faqs), [Terms of use](https://horse24.com/en/general-term-of-use)); the site refuses automated page fetches, so numbers below are the ones those pages state, not a full audit.

| HORSE24 | Ours |
|---|---|
| A platform for many **auction organisers**; each sale has its own conditions, fees and bid steps | One organiser (us) at first; per-auction conditions, fees and bid steps stored on the auction so a partner organiser can be added later |
| Two formats: **ONLINE** (timed, closes lot by lot) and **ONLIVE** (auctioneer runs the final bidding, bidders place regular bids only) | **Timed online only** in v1. Live/auctioneer mode = later |
| **Account + registration** before bidding; the operator may refuse a registration; legal age | Customer sign-in (phone + code, already built) + bidder registration per auction, approved by an admin |
| **Automatic (proxy) bids**: a maximum; the system bids in the smallest step to keep you on top, never past your limit | Same |
| **Bid steps** by auction and price, e.g. +250 up to 10,000, +500 above, +1,000 from 20,000; or one fixed step | Same: an increment table per auction (default table in settings) |
| **Anti-sniping**: any bid in the last **3 minutes** adds **3 minutes**; an extension does not move the next lot | Same, minutes configurable per auction |
| **Staggered closing**: lots end one after another | Same: lot *n* ends at `ends_at + n × gap` |
| Fees and the **total you would pay** shown before placing a bid | Same: the bid box shows hammer + buyer's premium + VAT |
| A bid binds only if it is highest at the close; an outbid bid lapses | Same, in our auction conditions |
| After the sale the auction office **invoices**, with a due date | Winner's invoice = a `ServiceOrder`, paid at `/pay/{order}` |
| **X-rays/vet reports** visible after registering | Vet documents visible to approved bidders only |
| Buyer arranges transport; stabling charged after a period | Out of scope v1; text in the conditions |

### 1.1 One real lot page, read in full

Lot 26 "Bond. James Bond", the 106th Online Auction Youngster and Broodmares (Westfälisches Pferdestammbuch, 2026-11), from a screenshot of the page.

**Auction header (branded per organiser)**: the organiser's colour, logo and "Hosted by …"; the auction's own menu (Online Auctions, Our Team, Contact Us, More); badge "Ended auction"; two dates: **"Starts at"** 19 Nov 12:00 (the catalogue goes online: browse, register, view documents) and **"Bid-up starts"** 23 Nov 22:30 (when bidding opens). Times are shown in the viewer's zone ("Ended on (GMT+4)").

**Lot navigation**: back to "Auction list", previous / next lot (Lot 25 · Lot 27), watch (bookmark), share (Facebook, X).

**Title**: lot number badge, name, one-line tagline ("Talented son of the sought-after Bonds").

**Media**: main photo + 3 thumbnails, YouTube videos (several, with thumbnails).

**Facts table**: gender, born (date), Life ID (UELN), breed, colour, genetic status (WFFS N/N), size (cm + hands), owner (name + town), **VAT rate for this lot (19%)**, breeder (name + town), responsible clinic.

**Locked documents**: Veterinary documents · X-rays · Auction terms, with the line "To view these documents, your registration must be completed".

**Pedigree**: a 4-generation bracket (sire with its studbook number, dam with number, colour and breed), with notable ancestors highlighted, then the **dam line** as text (licensed stallions and sport horses from the family, with results) and the dam's studbook data. A lot-specific note (here: the insurance covers death/emergency killing only).

**Bid board** (side panel): hammer price (€15,000), ended on date + time to the second, **"Sold to" + buyer's country flag** (SWE), **8 bidders · 27 bids**. Before the close: the current bid, countdown, and the bid box.

**"Other suggestions"**: lot cards (lot number + name, status badge, gender | born, sire / dam, hammer price + buyer country, bidders | bids, Details).

**Footer services**: bidding by phone, after-sale service, insurance cover, rent a rider, time schedule, charity auction.

What this changes in our plan:
- **Organisers are part of the design**: an auction belongs to an organiser (us, a stud, a club) with its own logo, colour, team and contacts. v1 can have only us, but the auction page is built with the organiser header from the start.
- **Two phases before the close**: catalogue online (`preview_at`), then bidding opens (`bidding_starts_at`), then the staggered close.
- **VAT per lot** (the seller's VAT status decides it), not only per auction.
- **Facts table** with UELN / microchip, genetic status, size, owner, breeder, clinic.
- **Results are public**: hammer price, buyer's country (never the name), bidders and bids counts. An admin can hide the price per lot ("price on request").
- **Bidding by phone**: an admin bids for a registered bidder who called in (marked "phone bid" in the log).

## 2. What we already have (reuse, do not rebuild)

- **Payments**: `Services\Payments\OrderPaymentService` (the only place payment state changes: row lock, idempotent), gateways Thawani / OMPAY / NBO / CCAvenue / Demo, checkout at `/pay/{order}`, the app's payment sheet. **The auction never talks to a gateway**: deposits and invoices are `ServiceOrder`s.
- **Money**: integer baisa (`OrderPricing` → `PriceBreakdown`), fee + VAT, refund rules (`OrderRefundService`, `OrderMoney`), receipts (`OrderReceiptPdf`), `IncomeStatement`, closed months.
- **Customers**: `customer` guard, phone + code (`CustomerOtpService`), CRM profile with the encrypted ID number (last 4 shown).
- **Sweeps**: `orders:expire-pending`, `orders:recover-late-payments` (Thawani is asked directly; never trust its redirect).
- **Notifications**: SMS (`SmsManager`), email, WhatsApp (bilingual in one message), push (`ExpoPush`, campaigns), admin-editable texts (`NotificationText`).
- **Admin**: Filament, Spatie roles, activity log, dashboard widgets skill, form-fields skill.
- **Horse data**: `Stallion` (pedigree keys, racing link), `HorseSalePost` (photos compressed, fields), racing profiles.
- **App remote control**: `MobileFeatures` (`on/hidden/soon`), push topics, website links.

## 3. Rules of the auction (to put in the auction conditions, EN/AR)

1. **Who can bid**: a signed-in customer with name, ID number and date of birth on the profile, who accepted this auction's conditions and paid the **bidding deposit** (if the auction asks one), approved by an admin (or automatically when the deposit is paid, per auction).
2. **Deposit**: a fixed amount per auction (e.g. 100 OMR), paid online. Not a winner → refunded after the sale. Winner → refunded once their invoice is paid (decided in phase 3: the order system has no "minus" line, and adding one would touch every receipt and report). Winner who does not pay → kept.
3. **Bids**: at least the current price + the step (the first bid = the starting price). A maximum bid becomes a proxy. Equal maximums: the earlier one wins. A bid cannot be withdrawn; an admin may void one (with a reason, logged, bidders told).
4. **Reserve**: hidden; the lot shows "Reserve not met / Reserve met". Not met at the close → not sold; the admin may offer the top bidder the lot at their bid ("post-sale offer") within 24 h.
5. **Close**: per lot at its own time, +N minutes for a bid in the last N minutes, until a quiet N minutes.
6. **Winner pays**: hammer + buyer's premium (%) + VAT on the premium (and on the hammer if the seller is VAT-registered: decide with the accountant) − deposit, within **X days** (e.g. 3). Not paid → deposit kept, the lot may go to the second bidder at their bid, the customer is blocked from bidding.
7. **Seller gets**: hammer − seller's commission (%) − fixed entry fee, after the buyer paid and the horse was handed over.
8. **Vet**: the seller's vet report and X-rays are on the lot for approved bidders; sold as seen.

## 4. Data model (`../hrs`)

All money columns `decimal(10,3)`, computed in baisa. All texts spatie-translatable (`en`/`ar`).

- `auction_organizers`: name, logo, colour, contacts, team (name, role, phone, photo), about. One row (us) seeded.
- `auctions`: organizer_id, title, slug, description, conditions (rich text), cover, `status` (draft → preview → live → closing → closed / cancelled), `preview_at` (catalogue online), `bidding_starts_at`, `ends_at` (first lot), `lot_gap_seconds`, `extension_minutes`, `deposit_amount`, `auto_approve_on_deposit`, `buyer_premium_percent`, `seller_commission_percent`, `entry_fee`, `payment_days`, `increments` (JSON table `[{from, step}]`), `organizer` (later).
- `auction_lots`: auction_id, `lot_number`, name, tagline, horse fields (sex, born date, UELN / microchip, colour, breed, height cm (hands shown too), genetic tests, owner name + town, breeder name + town, responsible clinic, `vat_rate`, sire/dam with studbook numbers + 4-generation pedigree (extend `Stallion::PEDIGREE_KEYS` to 4 generations), dam line text, lot note, racing_horse_id, location), `hide_result_price`, photos, video URLs, vet documents (private disk, signed links), seller (a `customer_id` + bank details encrypted), `starting_price`, `reserve_price` (never in a resource), `status` (draft → approved → live → sold / unsold / withdrawn / paid / handed_over), `ends_at` (with extensions), `current_price`, `leading_bid_id`, `bids_count`, `winner_customer_id`, `invoice_order_id`.
- `auction_bidders`: auction_id, customer_id, `status` (pending / approved / rejected / blocked), `deposit_order_id`, conditions accepted at + IP, `paddle_number` (shown instead of names: "Bidder 12").
- `auction_bids`: lot_id, bidder_id, `amount`, `max_amount` (proxy, never shown), `is_auto`, `placed_by_admin_id` (phone bids), `voided_at` + reason, `created_at` (microseconds: ties).
- `auction_watches`: customer_id, lot_id (watchlist + "notify me").
- `ServiceOrder` gets `auction_lot_id` (nullable) and a `kind` of `auction_deposit` / `auction_invoice` (use `serviceName()` for the label, like bookings).

## 5. The bidding engine (the part that must be right)

`Services\Auctions\PlaceBid` (one class, the only place bids are written):

1. In a DB transaction, `lockForUpdate()` the lot row (same pattern as `CreateStableBooking`'s slot lock).
2. Check: lot live and `now < ends_at` (server clock only), bidder approved for this auction, not the seller, amount ≥ `minimumNext()`, amount ≤ a sanity cap (e.g. 50× starting price, confirm box above 10×).
3. Resolve proxies: the new maximum vs the leader's maximum → the price becomes the lower maximum + one step (capped at the higher maximum); write the auto bids it implies.
4. If `ends_at − now < extension` → `ends_at = now + extension`.
5. Update `current_price`, `leading_bid_id`, `bids_count`; commit; then fire `BidPlaced` / `Outbid` events (queued notifications, after commit).

`Services\Auctions\CloseLot`: scheduled `auctions:close-due` **every minute** (+ `everyTenSeconds()` if the host's scheduler allows; also closed lazily on any read past `ends_at`). Locks the lot, re-checks `ends_at`, sets sold/unsold by the reserve, creates the winner's invoice order through `OrderPricing` (price = hammer, fee = buyer's premium, VAT, minus deposit), fires `LotWon` / `LotUnsold`. Idempotent.

`Services\Auctions\Settlement`: after the close, refunds losing bidders' deposits (recorded manually through `OrderRefundService` until gateway refunds exist; see "Not built yet" in `CLAUDE.md`), `auctions:expire-invoices` (daily) forfeits unpaid ones and lets the admin offer the second bidder.

Live updates: **polling** `GET /auctions/{slug}/lots/{lot}/state` every 3 s on the open lot page, 15 s on the list (`current_price`, `ends_at`, `server_now`, `bids_count`, reserve met, your status: leading / outbid). Shortcut: polling is enough for tens of bidders; move to Laravel Reverb (WebSockets) if one sale draws hundreds at once.

Tests (feature, `tests/Feature/Auctions/`): step table, proxy vs proxy, equal maximums, bid in the last minute extends, extension does not move the next lot, two bids at the same time (one rejected with "price changed"), bid after close refused, reserve not met, close idempotent, invoice amounts in baisa, deposit refunded/kept, seller cannot bid, unapproved bidder cannot bid, reserve never in JSON.

## 6. Payments (ready gateways, no new integration)

| Money | How |
|---|---|
| Bidding deposit | `ServiceOrder` kind `auction_deposit` → `/pay/{order}` (Thawani / OMPAY / whatever is ticked). `ServiceOrderReceived` listener approves the bidder when `auto_approve_on_deposit` |
| Winner's invoice | `ServiceOrder` kind `auction_invoice`, created at close, SMS/WhatsApp/push with the pay link, due in `payment_days` |
| Large amounts | Card gateways have per-transaction limits (ask Thawani / OMPAY / the bank for ours). Above the limit: **bank transfer**, recorded by an admin as paid (a "Record bank transfer" action that calls `OrderPaymentService`, with the transfer reference and a receipt upload) |
| Seller payout | A seller ledger like `StableStatement`: share = hammer − commission − entry fee; admin marks paid (bank transfer). Income = buyer's premium + commission + entry fee + VAT, in `IncomeStatement` |
| Refunds | Deposits of losers and cancelled lots through `OrderRefundService` (manual now; Thawani `POST /refunds` later: check Thawani's official docs first) |

## 7. Website pages (`../hrs`, Blade + Tailwind, EN/AR, RTL)

- `/auctions`: upcoming / live / past auctions (cards, countdown, lots count, badges).
- `/auctions/{slug}`: auction page with the organiser header (logo, colour, "Hosted by", catalogue date + bidding date, status badge) and its menu (lots, team, contact, conditions, time schedule); lots grid of cards as in §1.1 (lot no. + name, status badge, gender | born, sire / dam, current or hammer price + buyer country, bidders | bids, watch, Details); filters (gender, age, breed, sire).
- `/auctions/{slug}/lots/{lot}`: breadcrumb, auction list + previous/next lot, watch, share buttons (`<x-share-buttons>`), lot badge + name + tagline, gallery + YouTube videos, facts table (§1.1), locked documents (vet, X-rays, conditions; "complete your registration to view"), 4-generation pedigree bracket + dam line + lot note, racing record link; side **bid board**: before = current bid, countdown from `server_now`, bidders | bids, reserve badge, bid box (next minimum, quick-bid +1/+2/+5 steps, "set maximum", total-if-you-win incl. this lot's VAT), your status (leading / outbid); after = hammer price, ended at, sold to (country flag), bidders | bids; bid history (paddle numbers + time); "Other suggestions" (lots of the same auction).
- `/auctions/{slug}/team`, `/auctions/{slug}/contact`: the organiser's team and contacts (WhatsApp / call), a "Bid by phone" request.
- `/account/auctions`: my registrations, my bids (leading / outbid / won / lost), watchlist, invoices to pay, deposits.
- `/account/auctions/{slug}/register`: profile check, accept conditions, pay deposit.
- `/sell-at-auction`: seller application (horse details, photos, vet report, asking reserve) → admin review.
- Icons on every action and heading, statuses as badges, empty/error states (house rules). Every field with a bilingual placeholder, uploads with size/type hints (`form-fields` skill).

## 8. Admin (Filament, role `auction_manager`)

- **Auctions** resource: settings above, publish/schedule, "Start now", "Cancel" (refunds every deposit), lots relation manager with drag order (lot numbers).
- **Lots**: approve seller applications, reserve, withdraw (before/after bids, with reason), live view (price, bids, ends at), void a bid, extend/close manually, post-sale offer to the top/second bidder.
- **Organisers**: logo, colour, team, contacts (v1: one row, ours).
- **Phone bid**: place a bid for an approved bidder who called (logged with the admin's name).
- **Bidders**: approve / reject / block, ID check (last 4 + photo of ID if asked), deposit status.
- **Results & settlement**: invoice status, record bank transfer, mark handed over, seller payouts, exports (CSV/PDF results sheet).
- **Dashboard widgets** (dashboard-widgets skill): live lots, bids today, top lots, sell-through %, premium income.
- Activity log on auctions, lots, bidders and bid voids (`LogsStableActivity` pattern).
- Settings: default increments, extension minutes, premium %, commission %, payment days, deposit, notification texts (in Notification Texts).

## 9. Notifications (texts in `lang/*/auctions.php`, admin-editable)

Registration approved / rejected · auction starts (watchers + push topic `auctions`) · **outbid** (push + SMS once per 10 min per lot, WhatsApp optional) · lot ending in 15 min (watchers) · won (invoice link) · lost (deposit refund note) · invoice reminder (24 h before due) · invoice overdue · seller: lot approved, lot sold / unsold, payout sent. WhatsApp always English then Arabic in one message (`whatsapp-notifications` skill).

## 10. Mobile app (after the website works)

- API: `GET auctions`, `auctions/{slug}`, `auctions/{slug}/lots/{lot}`, `…/state`; `POST auctions/{slug}/register`, `POST lots/{lot}/bids`, `POST|DELETE lots/{lot}/watch`, `GET account/auctions` (customer token). Resources never carry `reserve_price` or `max_amount` of others. 409 `code: price_changed` with the new state; 422 for rule failures, messages in the request's language.
- Feature key `auctions` (+ `MobileFeatures::GROUPS`, `api/types.ts` `FeatureKey`), screens gated with `FeatureGate`, push topic `auctions` in `MobileDevice::TOPICS` and `PUSH_TOPICS`, CRM events (`auction_viewed`, `bid_placed`, `lot_won`) in `AppEvent::CLIENT` and `features/tracking/events.ts`.
- Screens: Services hub → Auctions; lot detail with sticky bid bar, polling while focused (`refetchInterval` 3 s), payment through the existing checkout sheet. JS only → ships over the air.
- Website links `/auctions/...` → app screens (`website-links.ts`; claiming them as universal links needs a store build).

## 11. Phases

Each phase ends with its tests passing (`composer test`, the admin sweep), this file and `CLAUDE.md` updated.

### Phase 1: bidding engine + admin ✅ (2026-11-24)

- [x] Tables `auctions`, `auction_lots`, `auction_bidders`, `auction_bids` (`2026_11_24_000001`), role `auction_manager` (`…000002`)
- [x] Rules without a database: `Services\Auctions\ProxyBidding` (proxy bids, equal maximums keep the earlier bidder, reserve jump), `BidIncrements`, `Money` (baisa)
- [x] `Services\Auctions\Bidding`: `place()` (lot row lock, who may bid, minimum, ×10 typo guard, anti-sniping extension per lot) and `void()` (the lot is worked out again from the remaining bids)
- [x] `Services\Auctions\CloseLot` + `auctions:close-due` every minute (sold when the reserve is met, else unsold; idempotent)
- [x] Admin → Auctions: auctions (schedule, bid steps, fees), lots tab, bidders tab (add, approve, reject, block), Lots (horse facts, 4-generation pedigree, photos, YouTube, private vet documents, prices), bid log with maximums (live, 10 s), **phone bid**, **void**
- [x] Tests: `tests/Unit/ProxyBiddingTest.php`, `tests/Feature/Auctions/*` (20 tests); admin sweep green in EN/AR
- Moved to later phases: the organisers table (phase 2, with the branded auction header), the demo seeder (phase 2, with the pages)

### Phase 2: website ✅ (2026-11-24)

- [x] `auction_organizers` (optional: an auction without one shows the site's own name) + admin → Auctions → Organisers; the branded auction header (colour, logo, "Hosted by", catalogue / bidding / first close dates)
- [x] `/auctions` (live / coming up / past), `/auctions/{slug}` (catalogue, search, gender filter, conditions), `/auctions/{slug}/lots/{n}` (gallery, YouTube, facts, locked documents, pedigree drawn to the generations filled in, dam line, note, bid board, history, other lots, share); live by polling (`…/state` every 3 s on a lot, `/auctions/{slug}/state` every 15 s on the catalogue, `server_now` for every countdown), lots past their time closed when read
- [x] Registration (`/auctions/{slug}/register`): name + ID number (kept on the customer, encrypted), the conditions accepted (time + IP), **deposit** as a `ServiceOrder` (`auction_bidder_id`) through `/pay/{order}`; `ApproveAuctionBidderOnDeposit` approves it when the auction has "Approve bidders automatically"
- [x] Bids from the website (JSON), confirm dialog, quick +1/+2/+5 steps, optional maximum, "total if you win" (`AuctionLot::buyerCosts()`), bidding watches the lot
- [x] Vet documents for approved bidders only (`auctions.document`, private disk)
- [x] SMS (texts in admin → Notification Texts → Auctions): approved, outbid (once per 10 min per lot), closes in 15 min (watchers, `auctions:remind-ending`), won
- [x] `/account/auctions` (my bids, watchlist, registrations + deposit order); `AuctionDemoSeeder`
- [x] Tests: `tests/Feature/Auctions/AuctionWebsiteTest.php` (9); checked in headless Chrome (EN/AR desktop)
- Not done: the losers' deposit refunds (phase 3, with the invoices), "sold to" country on results (we have no buyer country we trust yet), email/push/WhatsApp for bidders (SMS only; push comes with the app in phase 5), "Bid by phone" request form (admins can already place phone bids)

### Phase 3: settlement ✅ (2026-11-24)

- [x] Winner's invoice at the close (`Services\Auctions\Settlement::invoice`): a `ServiceOrder` with `auction_lot_id`: price = hammer + the lot's VAT, fee = buyer's premium + VAT, commission = seller's % + entry fee; due in `payment_days`; the "won" SMS links straight to its checkout; the lot page and "My auctions" show it with a pay button and the auction's bank transfer details
- [x] Kept out of `orders:expire-pending` (30 minutes); `auctions:invoices` (hourly): SMS a day before it is due, then the overdue flag
- [x] Admin → the lot → Settlement: invoice state, "Record bank transfer" (new payment method `bank_transfer`, never a checkout option), "Not paid: offer to the next bidder" (old invoice cancelled, deposit kept, bidder blocked; the next highest bidder at their highest bid gets a new invoice, or the lot becomes not sold), "Mark handed over", "Mark seller paid" (with the transfer reference); "To settle" filter on Lots
- [x] Seller's share fixed with the invoice (`payout_amount` = hammer + VAT − commission − entry fee); income (premium + VAT + commission) is in the income report like any order
- [x] Deposits: state per bidder (none / held / to refund / refunded / kept) on the Bidders tab; "Record deposit refund" (through `OrderRefundService`, the customer is emailed) and "Keep the deposit"
- [x] Tests: `tests/Feature/Auctions/SettlementTest.php` (6)
- Not done: refunds through the gateways' APIs (recorded by hand, as for every order: see "Not built yet" in `CLAUDE.md`); entry fees of lots that do not sell (owed by the seller outside the site); a seller statement PDF; automatic blocking across auctions (a defaulter is blocked in that auction only)

### Phase 4: seller applications ✅ (2026-11-24)

- [x] `/auctions/sell` (signed-in customers; linked from `/auctions` and "My auctions"): the horse, owner, lowest price they would accept, 1–10 photos (compressed), YouTube links, vet documents (private disk), a declaration; at most 5 waiting per customer and 5 sends a minute
- [x] Admin → Auctions → Seller applications (badge = waiting): fix typos, **Approve into a lot** (auction, lot number, starting price, reserve; a draft lot with the details, photos and documents, the customer as its seller; the admin finishes its page) or **Reject** with a reason
- [x] "My horses for sale" in "My auctions": waiting / not accepted (with the reason) / approved, then each lot's price, bids and result (in the auction, sold awaiting the buyer, buyer paid, paid out with the amount, not sold)
- [x] SMS to sellers: application accepted / not accepted, sold, not sold, payout sent (all editable in Notification Texts)
- [x] Tests: `tests/Feature/Auctions/SellerApplicationTest.php` (5); admin sweep green
- Not done: the seller choosing the auction (an admin picks it), the seller seeing bidders' numbers live beyond the public page, a seller statement PDF

### Phase 5: API + app ✅ (2026-11-24)

- [x] `/api/v1/auctions` (list, show with lots and `me`, lot with facts/pedigree/documents/state, `…/state` for polling, `{slug}/state`), `register`, `lots/{n}/bids`, `lots/{n}/watch` (POST/DELETE), signed `documents/{i}` links (30 min, approved bidders), `account/auctions` (bids with the winner's checkout, watchlist, registrations, selling, applications). Money as Money objects, texts in the request's language, 422s with `code`. `Services\Auctions\LotState` holds the rules the website and the API share.
- [x] Feature switch `auctions` (admin → Mobile app; registering and bidding refused by the server while off); CRM events `auction_viewed` (app), `auction_registered`, `bid_placed`, `lot_won` (server)
- [x] Messages also go as a **push** to the customer's signed-in phones (`SendAuctionMessage`, renamed from SendAuctionSms), tapping opens the lot, the auction or My auctions
- [x] App (`../hrs-mobile`): Services → Auctions, the auction (banner, dates, registration, lots refreshed every 15 s), the lot (live bid board every 3 s on the server's clock, bid with +1/+2/+5 steps and an optional maximum, "total if you win", watch, documents, pedigree, history, the winner's invoice in the payment sheet), register (name, ID, conditions, deposit in the payment sheet), My auctions (More and the auctions screen). Sellers apply on the website (`sell_url`). JS only: ships over the air.
- [x] Tests: `tests/Feature/Api/V1/AuctionsTest.php` (5); app `__tests__/auctions-test.ts`; checked in the web build (EN/AR) against the local API
- Not done: claiming `/auctions/*` as universal links (needs a store build; in-app website links already open the screens), the seller form in the app, a native (simulator) check

### Phase 6: pilot

- [x] Pilot test (2026-11-24): the whole buyer journey on the iPhone simulator in Arabic with OMPAY's sandbox (deposit with 3-D Secure, live outbid, close, invoice paid); two bugs found and fixed (registration was a modal the payment sheet could not open over; the bid confirmation's number format). Results: `docs/auction-pilot-runbook.md` §8
- [x] Go-live runbook: `docs/auction-pilot-runbook.md` (decisions, deploy, setting up the sale, a dry run on the live site, during and after the sale)
- [ ] Decisions in §13 and the runbook's §1 (licence, fees, VAT, card limits, conditions)
- [ ] Dry run on the live site with a real card (runbook §4), then the first real sale of 5–10 horses
- Later: WebSockets (Reverb) if a sale draws hundreds of bidders, live/auctioneer mode, partner organisers, refunds through the gateways' APIs

## 12. Docs to keep updated

- `../hrs/CLAUDE.md`: a new "Horse auctions" section (where things live, the bidding rules, money rules, scheduled commands added to the list) at the end of each phase.
- `../hrs-mobile/CLAUDE.md`: the endpoints table, a "Where things are" entry and a roadmap step when phase 5 ships.
- This file: tick phases, record decisions below.
- `store/privacy-policy.md` / `listing.md` if the app collects ID data for bidding.

## 13. Decisions needed before building

Phase 1 does not wait on these: each is a setting on the auction (defaults: no deposit, 0 %, 3 days to pay, steps in `Auction::DEFAULT_INCREMENTS`, 3-minute extension, 2 minutes between lots, bidders shown by number). They must be settled before phase 2 goes live.

1. Deposit: fixed amount, or none for verified customers? Auto-approve on deposit?
2. Buyer's premium %, seller commission %, entry fee, payment days.
3. VAT on the hammer price (seller VAT-registered or not): ask the accountant.
4. Card limits with Thawani / OMPAY for 1,000–50,000 OMR invoices; bank transfer as the main path above that?
5. Legal: does running public auctions in Oman need a licence or registration (Ministry of Commerce, Industry and Investment Promotion)? Ask a lawyer before the first live sale.
6. Who sells in v1: only horses the admin adds, or open seller applications?
7. Bidder names: paddle numbers only (recommended), or names visible?
8. Public results: show hammer price + buyer's country like HORSE24 (recommended), or hide prices?
9. Other organisers (studs, clubs, the racing club) running their own sales on Muhra: v1 or later?
