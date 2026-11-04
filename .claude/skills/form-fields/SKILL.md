---
name: form-fields
description: The rule that every form field has a bilingual placeholder showing what to type, and every upload a hint with the recommended image size, accepted types and size limit. Use when adding or changing any form field or file upload in the admin panel (/admin), the stable panel (/stable, including /stable/register), the website's forms, or the mobile app's forms.
---

# Form fields: always a placeholder, uploads always a size hint

Every text, email, phone, password, code, URL, textarea, rich text and select field has a placeholder, so people type the right thing the first time. Every upload says what to send.

- **Text-like fields**: an example of a real value, starting with "e.g." / "مثل:" (`e.g. 9123 4567`, `e.g. name@example.com`). Omani formats: mobile 8 digits starting 7 or 9, landline starting 2.
- **The example is in the field's own language**: an Arabic name field gets an Arabic example even in the English admin; instructions ("A few lines in Arabic") are in the admin's language. Latin values (emails, links, numbers, slugs) stay Latin.
- **Selects**: "Choose …" / "اختر …".
- **Passwords / codes / keys**: the rule, not an example (`At least 8 characters`, `6-digit code`, `Paste it from the gateway dashboard`).
- **Uploads**: recommended size and shape (`1600 × 900 px, landscape`; `Square, 400 × 400 px (shown in a circle)`), then the accepted types and the limit. Base the size on where the image is shown (check the Blade view / app screen).
- A **helper text** explains *why* or *how*; the placeholder only shows the shape of the value. Keep both when both help. Never put information the person needs after typing only in the placeholder (it disappears).

## How it works: one global rule (`App\Filament\Support\FieldPlaceholders`)

Registered in `AppServiceProvider` through Filament's `configureUsing`, so it covers **every panel and every form** (admin, stable, page builder blocks, relation managers, action modals) with nothing to add per screen:

- `TextInput`, `Textarea`, `RichEditor`, `TagsInput`: placeholder from `lang/{en,ar}/placeholders.php`, first `models.<model_snake>.<field>`, then `fields.<field>`. The field key is the name with `title.en` → `title_en` and a prefix dropped (`tamimah.password` → `password`, `accounts.thawani.credentials.secret_key` → `secret_key`). Disabled fields get none.
- `Select`: `choose` + its label ("Choose city"). Table filters keep their own "All".
- `FileUpload` (and `SpatieMediaLibraryFileUpload`): a hint next to the label: `dirs.<directory>` (slashes → `_`; used where one field name has several uses, e.g. `image` in `cms/columns` is an icon, in `cms/images` a full-width picture), else `models.*` / `sizes.<field>`, then the types from `acceptedFileTypes` and `maxSize` (else Livewire's 12 MB).
- **A field's own `->placeholder()` / `->hint()` wins** (it is set after the global one). Use that for one-off texts; the stable panel keeps its own `stable_panel.placeholders` group this way.

### Adding a field

1. Give the field a common name and it probably already works (`email`, `phone`, `slug`, `order`, `price`, `*_url`, `en_name`…).
2. If the generic text does not fit, add `models.<model>.<field>` (e.g. `models.farrier.en_name`); for a new kind of field, `fields.<field>`; for a new image, `sizes.<field>` or `dirs.<directory>`.
3. Add every key to **both** `lang/en/placeholders.php` and `lang/ar/placeholders.php`; `tests/Feature/AdminFieldPlaceholdersTest.php` fails when the key sets differ.

Left without a placeholder on purpose: date/time pickers (native pickers ignore it), toggles, checkboxes, radios, colour pickers, key-value fields.

## Elsewhere

- Mobile app (`../hrs-mobile`): `src/i18n/{en,ar}.ts` (`ar` is typed against `en`; a missing key fails `tsc`).
- Website (non-Filament) forms: the section's own `lang/{en,ar}/<section>.php`.
- On `TextEntry` / `TextColumn`, `->placeholder('—')` is the empty-value text, a different thing: leave those as they are.
- Check a rendered form: `Livewire::test(Page::class)->html()` (a full admin GET in tests fails on the sidebar's count badges when tables are missing), or `curl -s <url> | grep -o 'placeholder="[^"]*"'` on a page without sign-in (searchable selects render theirs in JS).
