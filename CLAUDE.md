# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A bilingual (English/Arabic, RTL-aware) horse-services marketplace and CMS-driven public website, built on Laravel 12 + Filament v4 (PHP ^8.2). The README is the stock Laravel one and says nothing about the project. Public pages are Blade views styled with Tailwind 4; all admin work happens in Filament panels.

## Commands

```bash
composer dev                  # php artisan serve + queue:listen + vite, via concurrently
npm run dev / npm run build   # Vite. `public/build` is gitignored, so JS/CSS changes need a build to show up
composer test                 # config:clear, then php artisan test
php artisan test --filter=RacingCalendarTest              # one test class
php artisan test tests/Unit/RacingParserTest.php          # one file
vendor/bin/pint               # code style (laravel/pint)
php artisan racing:warm       # pre-warm the racing handicap cache (not scheduled; add to cron if wanted)
php artisan transfer-posts:expire / horse-sale-posts:expire   # scheduled daily 00:05 / 00:10 in routes/console.php
```

`composer.json`'s `post-autoload-dump` runs `filament:upgrade`, so `composer dump-autoload` touches Filament assets.

The DB is MySQL (`hrs`); `hrse (1).sql` in the root is a dump. Queue and cache both use the `database` driver in `.env.example`, so `queue:listen` must be running for queued work.

### Testing gotchas

- phpunit uses in-memory sqlite, but **the migrations are MySQL-only** (`ALTER … MODIFY`), so `RefreshDatabase` fails. The stock `ExampleTest` files fail for this reason. Don't reach for `RefreshDatabase`.
- Racing feature tests instead use `tests/Concerns/FakesRacingSource`. It creates empty `cms_menus`/`cms_redirects` tables, pre-warms the `site_settings.all` cache, and answers `Http::fake()` from real captured responses in `tests/Fixtures/racing/`. `Http::fake()` stubs stack (first match wins), so use `replaceRacingFake()` to override mid-test.
- New site pages that render the shared layout need those same tables and that cache key, or the layout will hit a missing table.

## Architecture

### Three surfaces

1. **Public site** (`routes/web.php`, controllers in `app/Http/Controllers/Site/`, views in `resources/views/site/`, layout `components/layouts/site.blade.php`). Hardcoded routes for the directory/marketplace sections (stables, clinics, centers, shops, farriers, transfer-board, horses-for-sale, tools-for-sale, events, video-library, blog, racing).
2. **Admin panel** `/admin` (`AdminPanelProvider`): one Filament resource per model in `app/Filament/Resources/`, plus Shield (roles/permissions), activity log, backup/health, file explorer, API service, and the local `packstub/filament-form-builder` package (path repo in `packages/`, symlinked).
3. **"Public" Filament panel** `/transportation` (`PublicPanelProvider`, `app/Filament/Public/`): the user-facing transfer-post board built as a Filament resource.

### CMS-driven pages

`PageController` resolves a `CmsPage` by slug and renders its page-builder `content` JSON through `resources/views/cms/render-blocks.blade.php`. Block schemas live in `app/Filament/Blocks/Cms/CmsBlocks.php`; each block type has a matching `resources/views/cms/blocks/{type}.blade.php`. Adding a block means touching both.

Hardcoded routes (e.g. `/stables`) still take their SEO metadata from a published `CmsPage` with the same slug via `Support\Seo::forSlug()`.

The catch-all `/{slug}` route in `routes/web.php` excludes reserved prefixes with a big negative-lookahead regex. **Add any new top-level route prefix to that regex**, or `PageController` will shadow it. The `Route::fallback` then consults `CmsRedirect` rows before 404ing.

Site-wide settings come from two places: `SiteSetting` (cached under `site_settings.all`, used by layout/SEO/PDF logo) and `Setting` via `SettingsService` (key/value, SMTP config).

### Bilingual (EN/AR) — three coexisting patterns

- `config/languages.php` is the single source of truth for locales. `SetLocale` middleware (appended to the `web` group in `bootstrap/app.php`) reads `?lang=` then the session. The default locale is `APP_LOCALE`.
- **Simple models** (Country, Region, City, Type, Horse, Partner, SuccessStory): `en_x`/`ar_x` column pairs with locale-switching accessors.
- **CMS models** (CmsPage/Post/Category/MenuItem): `spatie/laravel-translatable` JSON columns.
- **Block content**: `{en:…, ar:…}` nested per field, read with `Support\Localized::value($data, 'key')` and built in forms with `Filament\Support\TranslatableInput::grid()`.
- UI strings live in `lang/{en,ar}/*.php`, one file per area (`admin_*.php` for admin, plain names for the public site). Add every string to both languages.
- **Filament slugs are always generated from the English field**, hardcoded (`$code === 'en'` inside a `TranslatableInput::grid`), never `TranslatableInput::defaultLocale()`. That reads `APP_LOCALE`, which differs between environments, and `Str::slug()` on Arabic gives empty or poor results.

### Racing database integration (`/racing/*`)

An external, HTTP-only ColdFusion racing site is scraped server-side and re-presented in our layout. Nothing is stored in our DB; everything is cached.

- `app/Services/Racing/`: `RacingClient` (HTTP + cache, the only thing that talks to the source), plus parsers (`RacingParser` search/profile, `RacingMeetingParser` race-day pages, `RacingHandicapParser` JSON grid, `RacingCalendarParser`), `RacingPdf` (mPDF), `RacingUnavailableException` for source outages. Config and TTLs are in `config/racing.php` (`RACING_*` env vars).
- Controllers `Racing*Controller`, views in `resources/views/site/racing/` and `components/racing/`, PDF views in `resources/views/pdf/racing/`, helpers `Support\RacingLinks`, `RacingSeo`, `PdfText`. Three CMS blocks embed it: `horse_search`, `race_calendar`, `race_widget` (the widget has a no-reload JS enhancement in `resources/js/race-widget.js` that falls back to plain links).
- Upstream hazards that are not obvious from the code:
  - Never send an unvetted race id to the meeting "shell" pages. An unknown id returns a 3–10 MB page in 25–60+ s. Validate first with the cheap `race.cfc?method=getRaceLink`/`raceInfo` call, as the existing controllers do.
  - Never send a malformed `dateFrom`/`dateTo` to the race-dates endpoint; it returns every race ever (~11 s). Only dates derived from a validated season are sent.
  - Search is Latin-only and the source keeps language in a session, so `lang` is sent explicitly on every call.
  - The source's "Local"/"Non-Local" handicap labels look inverted; that's upstream.
- PDFs: mPDF with the bundled Cairo font (static cuts in `resources/fonts/cairo/`; mPDF can't use variable fonts — see the README there), black/white/gray only (a test enforces it), QR code to the page URL. Use `PdfText::dir()` around values so Latin-in-Arabic text isn't bidi-flipped. mPDF can't do Tailwind/flex, so PDF views are plain tables.
- Print/PDF/share toolbar (`x-racing.tools`) appears on profile and race pages only.

### Other conventions worth knowing

- `AppServiceProvider` globally hooks every Filament `FileUpload` and Spatie media upload into `Support\ImageCompressor`, so images are compressed automatically.
- Posting forms on the public site (transfer-board, horses-for-sale, farriers, tools-for-sale) are guarded by `marcogermani87/filament-captcha` via a `/…/captcha` route each; posts expire via the scheduled commands above.
- Vite entry points (`vite.config.js`): `app.{css,js}`, `site.{css,js}` (public site), and `css/filament/admin/theme.css` (admin theme).
- `routes/api.php` is essentially empty; the API is generated by `rupadana/filament-api-service`.
