---
name: dashboard-widgets
description: How to build Filament dashboard and resource widgets in this app with laboiteacode/filament-dashboard-widgets (metric, goal, breakdown, trend, composition, recent items, timeline, funnel, bullet, variance, segment bar, usage limits, comparison chart, detail list, card) and the house rules every widget follows (permissions, caching, no polling, EN/AR text, money, RTL). Use when adding, changing or replacing any widget in the admin panel (/admin) or the stable panel (/stable), on a dashboard, a resource page or the Insights pages.
---

# Dashboard widgets

`laboiteacode/filament-dashboard-widgets` (MIT, v1) is installed and registered in both panels (`FilamentDashboardWidgetsPlugin::make()` in `AdminPanelProvider` and `StablePanelProvider`). It has no build step (its CSS is inlined). Full API: `vendor/laboiteacode/filament-dashboard-widgets/README.md`.

## Pick the widget

Every widget is an abstract class in `LaBoiteACode\FilamentDashboardWidgets\Widgets\*`; extend it and fill one method (value objects in `...\Data\*`).

| Need | Widget | Method |
|---|---|---|
| One KPI + trend % + sparkline | `MetricWidget` | `getMetric(): Metric` |
| Progress to a target | `GoalProgressWidget` | `getGoal(): Goal` |
| Split by status/type, no chart | `BreakdownWidget` / one bar: `SegmentBarWidget` | `getItems()` / `getSegments()` (`BreakdownItem[]`) |
| Time series (line/bar/area) | `TrendWidget` | `getTrend(): Trend` |
| Part-to-whole (doughnut/pie) | `CompositionWidget` | `getComposition()` |
| Actual vs target series | `ComparisonChartWidget` | `getLabels()`, `getSeries()` |
| Latest records + "View all" | `RecentItemsWidget` | `getItems()`, `getViewAllUrl()` |
| Activity feed | `TimelineWidget` | `getEvents()` |
| Record summary (key/value) | `DetailListWidget` | `getDetails()` |
| Conversion steps | `FunnelWidget` | `getStages()` |
| Change per category | `VarianceWidget` | `getItems()` (`VarianceItem`) |
| Value vs target with bands | `BulletWidget` | `getBullet()` |
| Quotas | `UsageLimitsWidget` | `getLimits()` |
| Stat / icon / content / CTA card | `CardWidget` | `getCard()` |

Plain counts still fit Filament's own `StatsOverviewWidget` (the existing `*Overview` widgets); use this package when a widget needs more than a number. Replace an existing widget only when the new one shows something better, and keep its permission, cache and sort.

## House rules (every widget)

- **Permission**: `public static function canView(): bool { return auth()->user()?->can('ViewAny:Model') ?? false; }` (Shield permission of the data it shows). Stable-panel widgets read the tenant's stable only.
- **No polling**: `protected ?string $pollingInterval = null;` (the package default is already off; never set one). Filament's 5 s polling re-runs every query per open tab.
- **Cache the query** with `App\Filament\Support\CachedWidgetCounts` (`totalAndMatching()` or `remember($key, fn)`, 5 minutes); cache plain arrays, not objects, and put the period (year/month) in the key.
- **Text in both languages**: headings, labels, descriptions from `lang/{en,ar}/admin_widgets.php` (stable panel: its own lang files), never literals. The package's own strings have Arabic in `lang/vendor/filament-dashboard-widgets/ar/widgets.php` (the package ships EN/FR only): add any key a package update brings.
- **Money**: `->formatUsing(fn ($v) => SiteSetting::formatCurrency($v))` (or `formatAmount`); never `Number::currency()` or a hardcoded `OMR` (see CLAUDE.md "Currency display").
- **Icons**: every widget gets a `heroicon-o-*` icon; colours from Filament's names (`primary`, `success`, `warning`, `danger`, `info`, `gray`).
- **Links**: `->url(SomeResource::getUrl(...))`, never a typed path.
- **Placement**: admin dashboard widgets in `app/Filament/Widgets` (discovered), stable panel in `app/Filament/Stable/Widgets`; widgets for one resource page in that resource's `Widgets/` folder, returned from `getHeaderWidgets()` and listed in the resource's `getWidgets()`. Set `$sort` and `$columnSpan`.
- **Tests**: a feature test that the page with the widget loads for a permitted user (and is hidden without the permission), like `tests/Feature/CachedWidgetCountsTest.php`.

## RTL caveat

The package's CSS positions a few markers with `left`: the bullet's target/benchmark ticks, the usage-limit warning tick, the timeline line and the card arrow icon. Check those widgets in the Arabic admin before shipping; if a marker sits on the wrong side, fix it with a small `[dir=rtl]` override in the panel's theme CSS, never by editing `vendor/` or publishing the views.

## Example: Notifications (push CRM) insights

Admin → Mobile app → Notifications (`PushCampaignResource`) is the reference for a resource page with insights, CleverTap-style:

- **Figures in one place**: `App\Support\MobileApp\PushInsights` returns plain cached arrays (`summary()`, `daily()`, `audience()`, `campaignOpens($campaign)`); the widgets only map them. Do the same for another page: a small `*Insights` class, not queries spread over the widgets. Days are Oman's (`BookingSlot::timezone()`), not UTC.
- **List page** (`ListPushCampaigns::getHeaderWidgets()`, 3 columns): `PushStatsOverview` (reach, delivered, opened, open rate vs the 30 days before, sparklines), `PushActivityChart` (`ComparisonChartWidget`: delivered vs opened per day), `PushFunnel` (targeted → delivered → opened), `PushAudience` / `PushTopics` (`BreakdownWidget`; `getTotal()` = all phones), `UpcomingPushCampaigns` (`RecentItemsWidget`, not cached: it is the queue). Tabs: all / campaigns / automatic / scheduled / drafts / sent.
- **Record report**: `ViewPushCampaign` with `CampaignFunnel` and `CampaignOpensChart` (`public ?PushCampaign $record` receives the record). Sent campaigns open there; editable ones open the form.
- **Control**: "Estimated reach" in the form (a `TextEntry` computed from the live form state through `PushCampaign::devices()`, so it applies the same rules as sending), "Pause" (scheduled → draft, guarded against the job that already claimed it), "Report".
- **Form controls** (create/edit): live preview (`resources/views/filament/push-campaign/preview.blade.php`, EN + AR banners), "Send a test" in the page header (`PushCampaignResource::testAction()`, sends the unsaved form), audiences `phones` / `stable_customers` / `stable_owners` (`target_phones`, `target_stables`; `MobileDevice::signedInWithPhones()`), Expo delivery fields (`PushCampaign::pushOptions()`: priority, ttl, silent, Android channel), quiet hours (`quietUntil()`: the job moves `scheduled_at` to 07:00 Oman), `ignore_cap`, `spread_minutes` (the job keeps `pending_devices`; `push:send-due` sends a batch a minute, claimed through `next_batch_at`). Tests: `PushControlsTest`.
- Filament closure gotcha: closures are injected **by parameter name**: `fn (Builder $query)`, never `fn (Builder $q)` (it gets an empty builder from the container and fails with `newQueryWithoutRelationships() on null`).
- Strings: `admin_widgets.push.*` (EN/AR). Tests: `tests/Feature/Api/V1/PushInsightsTest.php`.
