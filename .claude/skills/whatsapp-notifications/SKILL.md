---
name: whatsapp-notifications
description: How WhatsApp messages work in this app (Evolution API via wallacemartinss/filament-whatsapp-conector) and the rule that every WhatsApp message is bilingual (English then Arabic in one message). Use for any work that sends, adds, or changes a WhatsApp alert or notification: new post alerts, orders, bookings, refunds, reviews, test messages, or the General Settings → WhatsApp tab.
---

# WhatsApp notifications

## The rule: every WhatsApp message is bilingual

Every WhatsApp text this app sends contains **English first, then Arabic, in one message**, separated by `BilingualMessage::DIVIDER`. Build it with `App\Services\WhatsApp\BilingualMessage::make(fn () => …)`. The closure runs once inside each locale from `config('languages.available')`, so `__()` and locale-aware accessors (`$post->name`, `$city->name`) come out in the right language on their own. Never concatenate the two languages by hand, and never send one language only.

- Texts live in `lang/en/whatsapp.php` and `lang/ar/whatsapp.php`. Add every key to **both** files, with the same placeholders.
- Admin screen labels for WhatsApp live in `lang/{en,ar}/admin_whatsapp.php`.
- Arabic wording follows the site's own terms (e.g. `البياطرة`, `خيول للبيع`, `لوحة النقل`). Check `lang/ar/<section>.php` before inventing one.
- Money goes through `SiteSetting::formatCurrency($amount, 3)`, never a hardcoded `OMR`.
- Tests assert both halves: `[$en, $ar] = explode(BilingualMessage::DIVIDER, $message)`.

## How sending works

- `App\Services\WhatsApp\WhatsAppNotifier::send($to, $message, $type)`: the **only** place that calls Evolution. It normalizes the phone with `Support\PhoneNumber`, picks the instance from the settings (else the first connected one), writes a `notification_logs` row (`channel = whatsapp`) and **never throws**.
- Do **not** use the plugin's `Whatsapp` facade or `WhatsappService::sendText()`. Its `formatNumber()` prefixes `55` (Brazil) to any 10–11 digit number, and an Omani number with its country code (`968` + 8 digits) is 11 digits. Call `EvolutionClient` through the notifier.
- Send off the request with `App\Jobs\SendWhatsAppMessage::dispatch($phone, $message, $type)->afterCommit()`, one job per recipient, `$tries = 1` (a retry could send twice).
- `$type` is a short log key (max 32 chars): `new_horse_sale_post`, `test`, and later `order_received` and so on.
- Settings are read through `App\Support\WhatsAppSettings`: `enabled()`, `instanceId()` (a **UUID**: plugin instances use UUID keys), `recipients()` (normalized, de-duplicated), `alertEnabled($type)` (defaults to on).
- Evolution credentials are in `.env` (`EVOLUTION_URL`, `EVOLUTION_API_KEY`, …), never in the DB. The plugin's webhook route is `POST /api/webhooks/evolution` (its README says otherwise).

## Adding a new alert (e.g. an order received, a booking)

1. Fire a domain event, or reuse one: new posts use `App\Events\PublicPostSubmitted`, and orders already have `ServiceOrderReceived` / `ServiceOrderCompleted`.
2. Add a listener in `app/Listeners/`. It is auto-discovered; **never** also `Event::listen` it, which runs it twice. It checks `WhatsAppSettings::enabled()` and `alertEnabled('<type>')`, builds the text with `BilingualMessage`, wraps building in `try/catch` + `report()` so the user's action never fails, and dispatches `SendWhatsAppMessage` per recipient.
3. Put a message builder next to `App\Services\WhatsApp\NewPostAlert`: include the links the admin needs (the public page and the admin record via `Resource::getUrl('view', …, panel: 'admin')`).
4. Add the type to `WhatsAppSettings::ALERTS` and its label to `whatsapp.post_types` (or a new group) in both languages, so it gets a toggle in General Settings → WhatsApp.
5. Tests: extend `tests/Feature/WhatsAppAdminAlertsTest.php`. Use `Queue::fake()` for "who gets what", and `Http::fake(['evo.test/*' => …])` with `config(['filament-evolution.api.base_url' => 'https://evo.test', …])` plus `app()->forgetInstance(EvolutionClient::class)` for the real send path.
6. `bootstrap/cache/events.php` may exist locally or on the server: after adding a listener run `php artisan event:cache` (or `event:clear`), or the listener is silently never called.
