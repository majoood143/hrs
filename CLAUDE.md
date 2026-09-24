# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A bilingual (English/Arabic, RTL-aware) horse-services marketplace and CMS-driven public website, built on Laravel 12 + Filament v4 (PHP ^8.2). The README is the stock Laravel one and says nothing about the project. Public pages are Blade views styled with Tailwind 4; all admin work happens in Filament panels. On top of that sits a **paid service orders** system (forms → orders → payment gateways → customer accounts → notifications → income reports), described below.

## Commands

```bash
composer dev                  # php artisan serve + queue:listen + vite, via concurrently
npm run dev / npm run build   # Vite. `public/build` is gitignored, so JS/CSS changes need a build to show up (the admin theme too: new Tailwind classes in resources/views/filament/** only exist after a build)
composer test                 # config:clear, then php artisan test
php artisan test --filter=RacingCalendarTest              # one test class
php artisan test tests/Unit/RacingParserTest.php          # one file
vendor/bin/pint <paths>       # code style (laravel/pint); pass explicit paths, the tree has uncommitted work from other people
php artisan racing:warm       # pre-warm the racing handicap cache (not scheduled; add to cron if wanted)
php artisan form-builder:move-uploads-private [--dry-run]   # move old public form uploads to the private disk
```

Scheduled in `routes/console.php` (production needs a `schedule:run` cron **and** a queue worker): `transfer-posts:expire` (00:05), `horse-sale-posts:expire` (00:10), `orders:expire-pending` and `orders:recover-late-payments` (every 5 min), `model:prune` for `CustomerOtp` (daily).

`composer.json`'s `post-autoload-dump` runs `filament:upgrade`, so `composer dump-autoload` touches Filament assets.

The DB is MySQL (`hrs`); `hrse (1).sql` in the root is a dump. `.env.example` uses the `database` queue and cache drivers (a local `.env` may use `sync`); the order notifications are queued, so a worker must run wherever the driver is not `sync`. Mail defaults to `MAIL_MAILER=log`; SMTP details come from the admin "SMTP Settings" page, but the mailer itself must be `smtp` in `.env`.

After adding a resource or page, run `php artisan shield:generate --all`: Shield gates everything with `ViewAny:Model`-style permissions and only super-admins see a screen until they exist. Each new resource here has a policy following that pattern.

### Testing gotchas

- phpunit uses in-memory sqlite, but **the migrations are MySQL-only** (`ALTER … MODIFY`), so `RefreshDatabase` fails. The stock `ExampleTest` files fail for this reason (the one known failing test). Don't reach for `RefreshDatabase`.
- Instead, tests build only the tables they need. Traits in `tests/Concerns/`: `PreparesSiteLayout` (empty `cms_menus`/`cms_redirects`, pre-warmed `site_settings.all` cache, needed by anything rendering the site layout), `FakesRacingSource` (adds captured responses from `tests/Fixtures/racing/`), `PreparesOrderSite` (runs the real, portable order/fee/commission/notification-log migrations; `seedSiteSettings([...])` fakes site settings without a `site_settings` table), `MakesOrderForms` (form-builder tables + a linked form), `PreparesCustomerSite` (customers, SMS driver and Tamimah fakes), `MakesReviewOrders` (orders through review stages, `reviewer(['role'])` stub users with a `hasRole()`, `stagedOrder()`). Pick the highest one you need. Spatie's permission tables and the users table are portable, so a test that needs real roles can `require` their migrations.
- `Http::fake()` stubs stack (the first match wins): start from `Http::swap(new Factory)` or the helper (`replaceRacingFake()`, `tamimahAnswers()`) when overriding mid-test.
- Admin pages are tested with Livewire as a plain stub `Authenticatable` plus `Gate::before(fn () => true)` and `Filament::setCurrentPanel(...)`: the real `User` model queries the (MySQL-only) permission tables on every ability check.
- `phpunit.xml` raises `memory_limit` to 512M: every test boots an app, and the racing PDF logo guard (correctly) skips big logos when memory is short.
- Do not `Event::listen` anything in `app/Listeners`: Laravel auto-discovers that folder, so a manual registration runs each listener twice.

## Architecture

### Surfaces

1. **Public site** (`routes/web.php`, controllers in `app/Http/Controllers/Site/`, views in `resources/views/site/`, layout `components/layouts/site.blade.php`). Hardcoded routes for the directory/marketplace sections (stables, clinics, centers, shops, farriers, transfer-board, horses-for-sale, tools-for-sale, events, video-library, blog, racing), plus checkout (`/pay/*`, `/payment/*`), order status (`/orders/{number}`) and the customer area (`/account/*`).
2. **Admin panel** `/admin` (`AdminPanelProvider`): one Filament resource per model in `app/Filament/Resources/`, plus Shield (roles/permissions), activity log, backup/health, file explorer, API service, and the local `packstub/filament-form-builder` package (path repo in `packages/`, symlinked). Payments things live under the "Payments" navigation group.
3. **"Public" Filament panel** `/transportation` (`PublicPanelProvider`, `app/Filament/Public/`): the user-facing transfer-post board built as a Filament resource.

### CMS-driven pages

`PageController` resolves a `CmsPage` by slug and renders its page-builder `content` JSON through `resources/views/cms/render-blocks.blade.php`. Block schemas live in `app/Filament/Blocks/Cms/CmsBlocks.php`; each block type has a matching `resources/views/cms/blocks/{type}.blade.php`. Adding a block means touching both.

Hardcoded routes (e.g. `/stables`) still take their SEO metadata from a published `CmsPage` with the same slug via `Support\Seo::forSlug()`.

The catch-all `/{slug}` route matches only **single-segment** paths, and excludes reserved prefixes with a prefix-based negative-lookahead regex. A new single-segment top-level route must be added to that regex, or `PageController` shadows it; but adding a prefix blocks every CMS slug that starts with it (`pay` would block `payment-terms`). So new sections use two-or-more segment paths (`/account/orders`, `/pay/{order}`) and stay out of the regex. The `Route::fallback` then consults `CmsRedirect` rows before 404ing.

Site-wide settings come from two places: `SiteSetting` (cached under `site_settings.all`; layout/SEO/PDF logo, gateway, SMS, VAT settings) and `Setting` via `SettingsService` (key/value, SMTP config). Credentials go through `Support\SecretSetting` (encrypted with the app key, `enc:` prefix; legacy plain values still read).

### Bilingual (EN/AR) — three coexisting patterns

- `config/languages.php` is the single source of truth for locales. `SetLocale` middleware (appended to the `web` group in `bootstrap/app.php`) reads `?lang=` then the session. The default locale is `APP_LOCALE`.
- **Simple models** (Country, Region, City, Type, Horse, Partner, SuccessStory, Service): `en_x`/`ar_x` column pairs (Service keeps `name`/`description` as the English ones and adds `ar_name`/`ar_description`; use `localizedName()`).
- **CMS models** (CmsPage/Post/Category/MenuItem, ServiceFeeSetting, CommissionSetting): `spatie/laravel-translatable` JSON columns.
- **Block content**: `{en:…, ar:…}` nested per field, read with `Support\Localized::value($data, 'key')` and built in forms with `Filament\Support\TranslatableInput::grid()`.
- UI strings live in `lang/{en,ar}/*.php`, one file per area (`admin_*.php` for admin, plain names for the public site). Add every string to both languages.
- **Filament slugs are always generated from the English field**, hardcoded (`$code === 'en'` inside a `TranslatableInput::grid`), never `TranslatableInput::defaultLocale()`. That reads `APP_LOCALE`, which differs between environments, and `Str::slug()` on Arabic gives empty or poor results.
- Anything sent to a customer (SMS, email, receipt) is rendered in the order's stored `locale`, not the viewer's; `Support\Locale::within()` switches and restores.

### Paid service orders

The flow: a form-builder form linked to a `Service` → submission becomes a `ServiceOrder` → free orders are received at once, paid ones go to a gateway → payment confirmed → `ServiceOrderReceived` → customer email/SMS (+ reviewers), and the order enters review if its form has stages → stages approved (or one rejects, which ends the request and makes a refund due) → an admin completes it → `ServiceOrderCompleted` → customer told again. The customer signs in with phone + SMS code to see orders, documents and receipts.

**Money (the rules that are easy to get wrong).** Everything is 3-decimal OMR, computed in **integer baisa** (`Services\Orders\OrderPricing` → `PriceBreakdown`); columns are `decimal(10,3)`. `total = price + fee + VAT on price + VAT on fee`. The **service fee is added on top** of the price and is our income; VAT (default 5%, settings `vat.enabled|rate`) applies to price and fee. An optional **commission** (`CommissionSetting`) comes out of the client's share (the price), never added to the customer's total. Fee and commission rules resolve form > service > global, active and in date. All of it is **snapshotted on the order** at creation (amounts and the rule behind them), so editing a rule never changes an old order. All money lands in the client's single merchant account; `IncomeStatement` works out what the client owes us: `fee + VAT on fee + earned commission`. A refund returns price + its VAT, **never the fee**, and shrinks the commission proportionally (`OrderMoney::earnedCommission`). Free services (price 0) carry no fee, VAT or commission.

**Code map.**
- `app/Services/Orders/`: `OrderPricing`, `CreateServiceOrder` (also normalizes the phone), `OrderWorkflow` (review, approve, reject, complete), `OrderRefundService`, `OrderDocumentService`, `OrderReceiptPdf`, `OrderMoney`. `Models\ServiceOrder` (+ `OrderEvent` timeline, `OrderStage`, `OrderRefund`, `OrderDocument`, `PaymentGatewayLog`).
- `app/Services/Payments/`: gateways `Thawani`/`Nbo`/`CcAvenue`/`Demo` (ported from the booking apps under `~/Downloads/booking/`), `PaymentGateways` (enabled + configured ones), and `OrderPaymentService`, **the only place payment state changes** (row lock, idempotent, receipt numbers `RC-year-000001`). `Site\PaymentController` handles checkout, returns, webhook and callbacks. Never trust a redirect: Thawani is re-fetched from Thawani; NBO/CCAvenue callbacks must match the amount. Callback routes are CSRF-exempt in `bootstrap/app.php`. Every gateway call is logged (`payment_gateway_logs`, secrets redacted; its outcome scope uses MySQL JSON functions, so it is not testable on sqlite).
- Sweeps: `orders:expire-pending` cancels unpaid orders after `config('payments.pending_ttl_minutes')` (checking Thawani first); `orders:recover-late-payments` revives ones a Thawani session was paid for later (only system-cancelled ones; an admin-cancelled order that gets paid is flagged for a manual refund).
- Forms: the form-builder fork (see below) has a "Service & payment" tab (`Filament\Support\FormPaymentTab`, values in the form's `settings.payment|customer|notifications`), read through `Support\FormOrderSettings`. `Listeners\CreateOrderFromSubmission` creates the order and redirects the visitor to `/pay/{order}` (or the order page if free). A form whose service is inactive/deleted is closed; a price notice is shown above the form.
- Customers: `Models\Customer` on the separate `customer` guard (no password, cannot reach the admin). `Services\Auth\CustomerOtpService`: 6-digit code, 5 minutes, 5 tries, single use, keyed hash only, rate limits counted from the DB. Guest orders attach when their (verified) phone signs in. Phones are always stored as digits with country code (`Support\PhoneNumber`; 8 digits = Omani).
- SMS: `Services\Sms\SmsManager` (drivers `tamimah`, `demo` = show the code on screen for presentations, `log` = dev only), configured on the admin SMS Settings page, every attempt in `notification_logs`. `TamimahSmsGateway` follows the SOAP `SendSMS` description on tamimahsms.com; what its StatusCodes mean is unknown, so success = "Proccessed >= 1" unless "success codes" are set.
- Notifications: `Listeners\NotifyOnOrderReceived|Completed` → queued `Jobs\SendOrderNotification` (one per channel, per-form toggles, never throws, deduped by the log), `SendReviewerNotification` (a priced form's reviewers are emailed when the order is **paid**, not at submission; the package hook `holdNotificationsWhen` holds the plain email). Texts are in `lang/*/notifications.php`.
- Reports: `Services\Reports\IncomeStatement` (rows by day paid, exact baisa sums; free/unpaid excluded, refunded orders included) feeds the admin `IncomeReport` page, `StatementPdf`/`StatementCsv`, and the `IncomeOverview`/`IncomeChart` dashboard widgets.
- PDFs use `Services\Pdf\MpdfFactory` (Cairo font, Arabic shaping); receipts and statements are plain-table views in `resources/views/pdf/`.

**Review, rejection, refunds, documents.** A form's "Service & payment" tab can define ordered review **stages** (a name per language + a Spatie **role**; stored in the form's `settings.approval.stages`, plus `requires_document`). When an order is received, `Listeners\StartOrderReview` copies them into `order_stages` (a snapshot: editing or deleting the form never touches an order in review) and sets it `in_review`; `SendStageReviewNotification` emails the holders of the current stage's role. Only holders of the *current* stage's role (or `super_admin`) may approve or reject, row-locked, one stage at a time; the last approval sets `processing`. A rejection needs a reason (shown to the customer), ends the request, and notifies the customer; if it was paid, a **refund is due** (`ServiceOrder::refundDue()`). Completing needs no pending stage and, if the form asks, an uploaded document (`OrderWorkflow::completionBlockers`). This is our own small workflow, not `ffhs/filament-package_ffhs_approvals` (installed, unused): that package stores no comment/reason on a decision and has no reject-then-refund concept. Refunds are **recorded** by an admin after making them at the gateway or by bank transfer (`OrderRefundService`; there is no automatic gateway refund call): at most the price + its VAT minus earlier refunds, never the fee; full refund sets `payment_status = refunded`; each refund notifies the customer. Result documents live on the private `local` disk (`order-documents/{order}/`) and are served only by `OrderDocumentController`: to their own signed-in customer, or to an admin through a signed link. Watch out: relations with a default `orderBy` (events, refunds, stages) need `->reorder()->latest('id')` to get the newest row; plain `latest('id')` still sorts ascending first.

**Notification texts are admin-editable** (Settings → Notification Texts): customer-facing SMS and email texts are read through `Support\NotificationText::get('sms.completed', [...])` (an override saved in the `notifications.texts` site setting, else `lang/*/notifications.php`). Never call `__('notifications.…')` for those keys; only the two internal reviewer/stage emails still do. Saving refuses a text that drops a placeholder carrying the order number, link or amount (every default placeholder except `:site`/`:service`), or invents one; a text equal to its default is not stored. **VAT on the commission** is a setting (`vat.on_commission`, off by default, a tax question for the accountant): when on, `service_orders.vat_on_commission` is snapshotted at creation, shrinks with refunds like the commission, and is part of "due to us" (never of the customer's total); the report tables then show a "Commission + VAT" column via `IncomeStatement::commissionWithVat()`. Result documents can be replaced or deleted from the order page (`OrderDocumentService`, which only ever deletes inside that order's own folder).

**Not built yet:** automatic refunds through the gateways' APIs. Thawani's refund docs (a JavaScript Stoplight site) could not be read, and the one secondary source says Thawani refunds full payments only, while every refund here is partial (the fee is never refunded), so do not build it from guesses; refunds stay recorded manually. **Unverified against the real services:** Thawani's session `total_amount` is compared to the order total in baisa when present (a different unit would refuse every payment as `amount_mismatch`; check the first sandbox run's gateway log); NBO/CCAvenue are ports and untested live; Tamimah as above.

### The form-builder fork (`packages/packstub/filament-form-builder`)

An in-repo, permanently customized fork (bilingual fields, file upload field, site styling; see its `CHANGELOG.md`, which every change should extend). It depends on `App\Support\Localized`. Generic hooks the app uses, all on the `FormBuilder` facade: `registerFormTab`, `closedWhen`, `beforeForm`, `holdNotificationsWhen`, plus `SubmissionReceived::redirectTo()`. Uploads go to a **private** disk (`local`) and are reached only through a signed `form-files/{token}` route that checks the `viewAny` gate on the form model; the app's published `config/packstub-form-builder.php` must stay in step with the package's.

### Racing database integration (`/racing/*`)

An external, HTTP-only ColdFusion racing site is scraped server-side and re-presented in our layout. Nothing is stored in our DB; everything is cached.

- `app/Services/Racing/`: `RacingClient` (HTTP + cache, the only thing that talks to the source), plus parsers (`RacingParser` search/profile, `RacingMeetingParser` race-day pages, `RacingHandicapParser` JSON grid, `RacingCalendarParser`), `RacingPdf` (mPDF), `RacingUnavailableException` for source outages. Config and TTLs are in `config/racing.php` (`RACING_*` env vars).
- Controllers `Racing*Controller`, views in `resources/views/site/racing/` and `components/racing/`, PDF views in `resources/views/pdf/racing/`, helpers `Support\RacingLinks`, `RacingSeo`, `PdfText`. Three CMS blocks embed it: `horse_search`, `race_calendar`, `race_widget` (the widget has a no-reload JS enhancement in `resources/js/race-widget.js` that falls back to plain links).
- Upstream hazards that are not obvious from the code:
  - Never send an unvetted race id to the meeting "shell" pages. An unknown id returns a 3–10 MB page in 25–60+ s. Validate first with the cheap `race.cfc?method=getRaceLink`/`raceInfo` call, as the existing controllers do.
  - Never send a malformed `dateFrom`/`dateTo` to the race-dates endpoint; it returns every race ever (~11 s). Only dates derived from a validated season are sent.
  - Search is Latin-only and the source keeps language in a session, so `lang` is sent explicitly on every call.
  - The source's "Local"/"Non-Local" handicap labels look inverted; that's upstream.
- PDFs: mPDF with the bundled Cairo font (static cuts in `resources/fonts/cairo/`; mPDF can't use variable fonts — see the README there), black/white/gray only (a test enforces it for racing), QR code to the page URL. Use `PdfText::dir()` around values so Latin-in-Arabic text isn't bidi-flipped. mPDF can't do Tailwind/flex, so PDF views are plain tables.
- Print/PDF/share toolbar (`x-racing.tools`) appears on profile and race pages only.

### Other conventions worth knowing

- **Currency display is never a hardcoded `"OMR"` string.** General Settings has a `currency_code`/`currency_symbol` pair plus an optional `currency_icon` (SVG, disk `public`, directory `branding`, same upload pattern as `site_logo`/`app_logo`/`favicon`). Read it through `SiteSetting::currency()` (`['code','symbol','icon_url']`) or `SiteSetting::formatCurrency()`, and render it with the `<x-currency-symbol>` component, which shows the uploaded SVG icon when `currency_icon` is set and falls back to the plain `currency_symbol` text when it isn't. Any new blade view, widget, or PDF that displays a money amount must use this component (or `SiteSetting::currency()`) instead of printing `OMR`/a bare symbol, so it picks up an admin's currency icon upload automatically. The `CURRENCY`/`'OMR'` constants inside the payment gateway classes (`CcAvenueGateway`, `NboGateway`) are wire-format codes for those APIs, not display text, and are exempt.
- **Every new or reworked public page gets visuals, not just text.** Use Heroicons (`<x-heroicon-o-…>`, from `blade-ui-kit/blade-heroicons`, already installed through Filament; `<x-dynamic-component :component="…">` when the name is computed) on every action button (download, pay, print, back, …) and on section headings, show statuses as cards/badges with an icon tinted by the enum's `color()` (success = emerald, info = sky, warning = amber, danger = red, gray = warm), and give lists/timelines icon markers instead of plain lines. Icons are decorative (`aria-hidden="true"`), the text label always stays. Directional icons (arrows, chevrons) need `rtl:rotate-180`. Write full literal Tailwind class names (no string-built `bg-{$tone}-50`) so the build finds them, and run `npm run build`. The order pages' `site/orders/_status-cards` and `_timeline` partials are the reference. PDFs stay black/white/gray tables (mPDF).
- **Order timeline text** comes from `OrderEvent::label()`, never `$event->message`: the message is saved in the language of whoever caused the event (usually an admin's English), while `label()` re-translates every type that has an `orders.events.*` string into the viewer's language.
- `AppServiceProvider` globally hooks every Filament `FileUpload` and Spatie media upload into `Support\ImageCompressor`, so images are compressed automatically, and wires the form-builder hooks above.
- Posting forms on the public site (transfer-board, horses-for-sale, farriers, tools-for-sale) are guarded by `marcogermani87/filament-captcha` via a `/…/captcha` route each; posts expire via the scheduled commands above.
- Vite entry points (`vite.config.js`): `app.{css,js}`, `site.{css,js}` (public site), and `css/filament/admin/theme.css` (admin theme).
- `routes/api.php` is essentially empty; the API is generated by `rupadana/filament-api-service`.
