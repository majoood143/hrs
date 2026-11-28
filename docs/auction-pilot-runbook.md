# Auction pilot: go-live runbook

For the first real sale (5–10 horses), after phases 1–5 of `docs/horse-auctions-plan.md`.
Each step says who does it. Tick it off in a copy of this file, or in the pilot's ticket.

## 1. Decisions that must be made first (owner, accountant, lawyer)

- [ ] **Licence**: does running public auctions in Oman need a licence or a registration (Ministry of Commerce, Industry and Investment Promotion)? Get it in writing before the sale is announced.
- [ ] **Fees**: buyer's premium %, seller's commission %, entry fee per horse, days to pay (default 3).
- [ ] **VAT**: whether the hammer price carries VAT (per horse: the seller's VAT status) and the rate on our fees. Set `vat_rate` on each lot accordingly.
- [ ] **Deposit**: amount (the pilot test used 10 OMR) and whether bidders are approved automatically once it is paid, or by hand.
- [ ] **Card limits**: ask Thawani and OMPAY the most one card payment may be. Above it, winners pay by bank transfer: write the bank details in the auction's "Bank transfer details".
- [ ] **Conditions**: the auction conditions (EN + AR), checked by the lawyer: binding bids, fees, payment deadline, what happens when a winner does not pay (deposit kept, offer to the next bidder), hand-over, vet documents "as seen".
- [ ] **Results**: show the hammer price on the site after the sale, or hide it per horse.

## 2. Deploy (developer)

Backend (`../hrs`), on the server:

```bash
php artisan migrate --force            # 2026_11_24_* … 2026_11_27_* (auctions, roles, settlement, applications)
php artisan shield:generate --all --panel=admin --ignore-existing-policies
npm ci && npm run build                # the auction pages' CSS and auction-live.js
php artisan optimize                   # config, routes AND events (the deposit listener is event-discovered)
```

- [ ] The scheduler runs every minute (`* * * * * php artisan schedule:run`) and a queue worker runs: `auctions:close-due` and `auctions:remind-ending` (every minute), `auctions:invoices` (hourly), and every auction SMS/push are queued jobs.
- [ ] `SMS Settings`: the real driver (`tamimah`), not `demo`. Send a test SMS.
- [ ] `Payment Gateways`: the gateway(s) for the pilot ticked and **in live mode** with live keys; the demo gateway is never offered on a live site. "Bank transfer" is not a checkbox there (an admin records it on the invoice).
- [ ] `Notification Texts → Auctions (SMS)`: read every text in both languages; the links must stay in them.
- [ ] Give the auction staff the `auction_manager` role (Users).

App (`../hrs-mobile`), JS only:

- [ ] `npx tsc --noEmit && npx expo lint && npx jest`, then `scripts/publish-update.sh production "Auctions"` (it refuses uncommitted changes and checks the fingerprint).
- [ ] Admin → Mobile app → Features: `auctions` = **Soon** until the sale is published, then **On**. Off = hidden everywhere, and the server refuses registering and bidding.

## 3. Set up the sale (auction manager, in the admin)

- [ ] Admin → Auctions → Organisers: only if a partner runs it (logo, colour). Ours: leave empty.
- [ ] Admin → Auctions → New auction: titles EN/AR, slug, cover, conditions EN/AR, dates (catalogue online, bidding opens, first lot closes; Oman time), gap between lots (e.g. 120 s), extension (3 min), bid steps, deposit, auto-approve, fees, days to pay, bank transfer details. Status **Draft**.
- [ ] Lots: one per horse (or from Seller applications → Approve into a lot): **Arabic name** (an approved application copies the English one), tagline, facts, pedigree, dam line, photos (≥ 3), YouTube videos, vet report and X-rays (private), starting price, reserve, VAT rate, the seller's account. Status **In the catalogue**.
- [ ] Preview every lot page on the website (EN and AR) and in the app.
- [ ] Publish (status **Published**) when the catalogue should go online.

## 4. Dry run on the live site (before announcing)

With a test auction on the live site (status Published, one lot closing 15 minutes ahead, the deposit set to the smallest amount the gateway accepts), and a real phone:

- [ ] Sign in, register, **pay the deposit** with a real card → "You are registered", the SMS arrives.
- [ ] Bid from the app and from the website; outbid yourself from the other one → the board updates within 3 s, the outbid SMS and push arrive.
- [ ] The lot closes → the "won" SMS and push with the invoice link; pay the invoice → paid in admin → the lot's Settlement tab.
- [ ] Record the deposit refund (and send the money back through the gateway's dashboard); refund the invoice the same way.
- [ ] Delete the test auction (nothing else depends on it once its orders are refunded) or set it to Cancelled.

## 5. During the sale (auction manager)

- Watch Admin → Auctions → Lots (sorted by close time) and each lot's **Bids** tab (refreshes every 10 s, shows each bidder's maximum).
- Bidders waiting for approval: the auction's **Bidders** tab (when auto-approve is off).
- **Phone bids**: the lot page → Phone bid (same rules as online, logged with your name).
- **A mistaken bid** (an extra zero): only after confirming with the bidder, Bids → Void with the reason; the lot is worked out again.
- Problems with messages: Admin → Notification logs (type `auction_*`).

## 6. After the sale (auction manager, finance)

- Each sold lot: Lots → filter **To settle**.
- Invoice paid online: nothing to do. Paid by bank transfer: Settlement → **Record bank transfer** with the reference.
- A day before the due date the winner gets a reminder; after it the lot shows **Overdue**: call them, then if needed **Not paid: offer to the next bidder** (their deposit is kept, they are blocked in this auction).
- Hand-over done: **Mark handed over**. Seller paid (bank transfer of the "Seller's share"): **Mark seller paid** with the reference: the seller gets an SMS.
- Deposits: the auction's Bidders tab. **To refund** → send the money back through the gateway's dashboard, then **Record deposit refund** (the customer is emailed). **Kept** = an unpaid invoice.
- Income: Admin → Income report (buyer's premium + VAT, commission + entry fees).

## 7. Known limits for the pilot

- Refunds are not sent through the gateways' APIs: recorded by hand, money returned from the gateway's dashboard (as for every order on the site).
- Prices refresh by polling (3 s on a lot, 15 s on the catalogue): fine for tens of bidders per lot; WebSockets (Laravel Reverb) if a sale draws hundreds at once.
- Entry fees of unsold horses are not tracked; a defaulter is blocked in that auction only.
- `/auctions/*` links open the app only from inside it (claiming them as universal links needs a store build).
- Sellers apply on the website only (photos and vet documents), not in the app.

## 8. Pilot test results (2026-11-24, local server + OMPAY sandbox, iPhone 18 Pro simulator, Arabic)

A new customer (+968 90000009) on the iPhone 18 Pro simulator, app in Arabic, local server, OMPAY's sandbox (the only gateway configured locally), demo auction with a 10 OMR deposit and auto-approval.

| Step | Result |
|---|---|
| Sign in (phone + code) | ✅ |
| Register (name, ID, conditions) → deposit in the payment sheet | ❌ then ✅: the sheet never opened, the button spun forever (order created, unpaid). **Fixed**: registration was a native modal and iOS cannot open the payment sheet over one; it is now a pushed screen. Retried from the auction's "Pay the deposit" button |
| Deposit paid on OMPAY's page, card with 3-D Secure OTP | ✅ sheet closed by itself, "You are registered, bidder 4", approved, receipt RC-2026-000017, "approved" SMS in Arabic |
| Bid (empty box = the minimum), "total if you win" | ✅ 1,500 → 1,578.750 (+5% premium + VAT on it), confirm dialog, board updated |
| Outbid from another bidder (server side) | ✅ the app switched to "Outbid", 1,600, next minimum 1,650 within 3 s; "outbid" SMS in Arabic |
| Bid back (1,650), lot closes | ✅ countdown red under 3 min; closed on the next poll: "Sold", "You won", invoice notice; "won" SMS with the total, due date and checkout link |
| Invoice paid on OMPAY (card without OTP) | ✅ 1,736.625 (1,650 + 82.5 premium + 4.125 VAT), receipt RC-2026-000018, seller's share 1,567.500, "Your invoice is paid" |
| My auctions | ✅ the lot (Sold, You won), the registration (bidder 4) |
| Deposit of the winner | ✅ "held" until the whole sale has ended (other lots still open) |

Also found and fixed: the bid confirmation showed the raw amount (1500.000) instead of the site's format (1,500.000). Seen in the pilot: the site's settings cache on the local database had been emptied (no SMS driver, no gateways) until `cache:forget site_settings.all`; check it on the server before the dry run.

Not covered by this run (covered by the automated tests): the admin's settlement buttons, deposit refunds, the overdue path and the offer to the next bidder; Thawani (not configured locally); the website's bid form in a real browser; push delivery (the unsigned simulator build has no push entitlement).
