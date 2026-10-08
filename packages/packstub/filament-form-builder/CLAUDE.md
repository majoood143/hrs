# packstub/filament-form-builder

Free Filament v4/v5 plugin: a form builder — forms designed in a Filament resource, rendered on the site with Blade, Livewire or a JSON API, submissions stored, emailed, exported and passed to sinks.

## Commands

```bash
composer test               # Pest suite
composer test:filter <name>
composer lint               # Pint
```

## Layout

- `src/Fields/` the field system: `Field` (value object built from the stored JSON), `FieldType` (base class: editor schema, rules, normalisation, Blade view, Filament component), `FieldTypeRegistry`, `Types/` the built-ins.
- `src/Support/FieldConditions.php` conditional fields: the server applies the rules (hidden fields are not validated and stored as null); the Blade script and Livewire renderer only mirror them. `FormTemplates` (new-form templates), `FormPreview` (the editor preview).
- `src/Submissions/` the pipeline: `Submitter` (availability → spam → validation → store → events), `SpamGuard` + `ProtectionToken` (honeypot, time trap), `SubmissionContext`, `SubmissionResult`.
- `src/Http/` `SubmitFormController` (HTML redirect back or JSON), `FormDefinitionController`, `ShowFormController` (hosted page), `FormState` (success / errors / old input from the session or the `fb_success` / `fb_state` query params).
- `src/View/Components/Form.php` + `resources/views/components/form.blade.php` + `resources/views/fields/*` the Blade renderer; `resources/css/form-builder.css` and `resources/js/form-builder.js` are inlined once per page.
- `src/Livewire/FormBuilderForm.php` the Livewire renderer (`<livewire:form-builder>`).
- `src/Filament/` `FormResource` (a General section, then Tabs: Fields / Settings / Embed), `FieldBlocks` (the Builder: grouped, described "Add field" menu; fine-tuning under "More options"; "Show this field only when" rules), `EditorLanguages` (the editor's All/EN/AR switch: use `EditorLanguages::grid()` for any new bilingual editor input), `Pages\ManageSubmissions` (a form's submissions, a tab next to the editor), `SubmissionsCsv`.
- `src/FormBuilder.php` (+ facade): field type registration, sinks, `find()`, `submit()` from code. `FormBuilderPlugin` registers types and the resource on a panel.
- `config/packstub-form-builder.php`, `database/migrations/create_form_builder_tables.php.stub`, `resources/lang/en/form-builder.php` (every UI string).
- `docs/` customer docs, synced to packstub.dev by CI.

## Conventions

- Every change needs a test and a `CHANGELOG.md` line.
- No hard dependency beyond Filament and `spatie/laravel-package-tools`; anything optional is detected, never required.
- The Blade renderer must keep working without a session and without JavaScript; the Livewire renderer needs Filament's frontend assets on the page.
- Migrations are additive: a schema change lands in `create_*` and in a guarded `add_*` migration; never rename or drop a column inside a major.
