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
- **Nationality field type** (`Types\NationalityField`): a searchable dropdown sourced from the app's public `Country` list (`nationality_en`/`nationality_ar`) instead of builder-entered choices — a Filament `Select` with `->searchable()` on the Livewire renderer. Added `FieldType::fixedChoices()`, a hook a type can use to supply its own value => label list instead of the field's `choices` option; `Field::choices()` consults it first.
- **`initCombobox` in `form-builder.js`**: the plain Blade renderer's base markup for a "nationality" field is an ordinary `<select>` (works with no JS, keyboard type-ahead included); the enhancement script (only present when `frontend.enhance` is on) upgrades it into a mobile-first searchable combobox — a text input that filters the options live, backed by a results panel that always opens downward and scrolls internally (`max-height:min(60vh,320px)`) instead of the browser's own select/datalist popup, which on a ~200-item list can flip upward and run off the top of the screen. The `<select>` stays the real, constrained form control (visually hidden once enhanced); the visible text can only ever be set to one of its option labels, so there is no way to submit a suggestion the visitor kept typing past (e.g. "Omani" -> "Omanisdjfs").
- **Radio buttons with details field type** (`Types\ConditionalRadioField`, id `conditional_radio`): radio buttons plus a follow-up text box (short or long) that only appears for the answers chosen in "Show the details box for" (e.g. "Did you travel last year?" No / Yes → "Where to?"). One field, one key: the value is stored as `{answer, details}` and printed as "Yes — Italy"; details typed and then left behind by switching to a non-revealing answer are dropped. The plain renderer shows and hides the box with CSS `:has()` alone, and the enhancement script (`initConditional`) toggles its `required`/`disabled`; the Livewire renderer is a `Group` with a live `Radio` and a conditionally visible input. Added the generic `FieldType::nestedRules()`/`nestedAttributes()` hooks (rules and message names for parts of a structured value, merged by `Form::validationRules()`/`validationAttributes()`), `FormState::error()` now also returns an error on such a part (`key.answer`), and `HasChoices::choicesRepeater()` can make choice values live so another setting can list them.

### Changed

- `HasChoices` choices editor moved from a flat `KeyValue` to a `Repeater` of `{value, label: {en, ar}}` rows to support bilingual choice labels.

## 1.0.0 — 2026-09-09

### Added

- **Forms resource**: build forms in the panel with a block per field type (text, email, phone, URL, number, long text, dropdown, radio buttons, checkbox, checkbox list, date, hidden, heading, paragraph), each with label, key, placeholder, help text, default, required, width and extra Laravel rules; settings for the submit button, success message, redirect, notification emails, storing submissions, an availability window, login requirement and spam protection; an Embed tab with copyable snippets.
- **Submissions**: stored with the values keyed by field key plus a snapshot of the labels, the page they came from, the channel, IP and user agent (both optional); a relation manager with a details slide-over, read / unread state, filters, bulk actions and a CSV export without extra packages; an unread badge on the navigation item.
- **Three renderers** on one submission pipeline: the `<x-form-builder::form>` Blade component (plain HTML, session-less friendly, optional in-place fetch submission), the `<livewire:form-builder>` component (Filament fields, in-place validation) and a JSON API (`GET /forms/{slug}/definition`, `POST /forms/{slug}` with `Accept: application/json`); a hosted page per form.
- **Spam protection** without a captcha: a honeypot, a time trap (encrypted token issued with the form) and a per-IP rate limit; dropped submissions fire `SpamDetected`.
- **Extending**: custom field types (`FieldType`), submission sinks (`SubmissionSink`) for CRMs and webhooks, the `SubmissionReceived` event, `FormBuilder::submit()` from code, swappable models and table names, CSS variables for theming.
