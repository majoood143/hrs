# Changelog

All notable changes to `packstub/filament-form-builder` are documented here.

## Unreleased — forked in-repo (hrs)

Vendored into `packages/packstub/filament-form-builder` as a local Composer path package so the app can carry permanent, uncommitted-upstream customizations without `composer update` ever reverting them.

### Added

- **Bilingual EN/AR content**: form name/description/submit label/success message (via `Spatie\Translatable`) and every field's label/placeholder/hint/choice labels (stored as `{en, ar}`, resolved with `App\Support\Localized`), edited with `App\Filament\Support\TranslatableInput` — the same pattern the app's CMS module uses.
- **File upload field type** (`Types\FileField`): accepted extensions and max size configurable per field in the builder; stores to a plain filesystem disk (`config('packstub-form-builder.uploads')`) from either the plain Blade POST or the Livewire renderer.
- **Brand styling**: `resources/css/form-builder.css` retinted to the site's warm palette and fonts; RTL-safe logical properties; the hosted page layout now loads the site's own stylesheet and sets `dir` from the locale.

- **Extension points for the host app**: `SubmissionReceived` can now steer what the visitor sees next (`redirectTo()`, `$message`, `$extra` merged into the JSON response, carried by `SubmissionResult`); `FormBuilder::registerFormTab()` adds a tab to the form editor; `FormBuilder::closedWhen()` closes a form for an app-specific reason; `FormBuilder::beforeForm()` renders markup above the fields in both the Blade and Livewire renderers. The app uses them to turn submissions of a form linked to a service into (paid) orders.
- **`FormBuilder::holdNotificationsWhen()`** keeps the "new submission" email back for a form (the host app holds it for an unpaid order and sends its own once it is paid).
- **Private file uploads**: uploads go to a private disk (`uploads.disk`, default `local`) and are reached through a signed download route (`form-files/{token}`) that also checks the `viewAny` gate on the form model, always served as an attachment and only inside the uploads directory. Earlier uploads on `uploads.legacy_disks` (`public`) are still served, and `php artisan form-builder:move-uploads-private` moves them.
- **Default extension deny-list**: a file field with no `accepted_types` allow-list of its own now still rejects server-executable/script extensions (`uploads.blocked_extensions`: `.php`, `.phtml`, `.exe`, `.sh`, ...), on both the plain Blade path (`FileField::rules()`) and the Livewire path (`FileUpload::rules()`), so an unconfigured field never silently accepts any file type.

### Changed

- `HasChoices` choices editor moved from a flat `KeyValue` to a `Repeater` of `{value, label: {en, ar}}` rows to support bilingual choice labels.

## 1.0.0 — 2026-09-09

### Added

- **Forms resource**: build forms in the panel with a block per field type (text, email, phone, URL, number, long text, dropdown, radio buttons, checkbox, checkbox list, date, hidden, heading, paragraph), each with label, key, placeholder, help text, default, required, width and extra Laravel rules; settings for the submit button, success message, redirect, notification emails, storing submissions, an availability window, login requirement and spam protection; an Embed tab with copyable snippets.
- **Submissions**: stored with the values keyed by field key plus a snapshot of the labels, the page they came from, the channel, IP and user agent (both optional); a relation manager with a details slide-over, read / unread state, filters, bulk actions and a CSV export without extra packages; an unread badge on the navigation item.
- **Three renderers** on one submission pipeline: the `<x-form-builder::form>` Blade component (plain HTML, session-less friendly, optional in-place fetch submission), the `<livewire:form-builder>` component (Filament fields, in-place validation) and a JSON API (`GET /forms/{slug}/definition`, `POST /forms/{slug}` with `Accept: application/json`); a hosted page per form.
- **Spam protection** without a captcha: a honeypot, a time trap (encrypted token issued with the form) and a per-IP rate limit; dropped submissions fire `SpamDetected`.
- **Extending**: custom field types (`FieldType`), submission sinks (`SubmissionSink`) for CRMs and webhooks, the `SubmissionReceived` event, `FormBuilder::submit()` from code, swappable models and table names, CSS variables for theming.
