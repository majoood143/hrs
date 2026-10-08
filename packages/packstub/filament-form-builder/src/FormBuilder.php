<?php

namespace Packstub\FormBuilder;

use Filament\Tables\Contracts\HasTable;
use Packstub\FormBuilder\Contracts\SubmissionSink;
use Packstub\FormBuilder\Fields\FieldType;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\SubmissionResult;
use Packstub\FormBuilder\Submissions\Submitter;

class FormBuilder
{
    /** @var array<int, class-string<SubmissionSink>|SubmissionSink> */
    protected array $sinks = [];

    /** @var array<int, \Closure(): mixed> */
    protected array $formTabs = [];

    /** @var array<int, \Closure(Form): ?string> */
    protected array $closedChecks = [];

    /** @var array<int, \Closure(Form): ?string> */
    protected array $beforeForm = [];

    /** @var array<int, \Closure(Form): bool> */
    protected array $holdNotifications = [];

    /** @var array<string, array{page: class-string, path: string}> */
    protected array $resourcePages = [];

    /** @var array<int, \Closure(): mixed> */
    protected array $recordActions = [];

    /** @var array<int, \Closure> */
    protected array $submissionActions = [];

    public function __construct(protected FieldTypeRegistry $types) {}

    /** @return class-string<Form> */
    public static function formModel(): string
    {
        return config('packstub-form-builder.models.form', Form::class);
    }

    /** @return class-string<FormSubmission> */
    public static function submissionModel(): string
    {
        return config('packstub-form-builder.models.submission', FormSubmission::class);
    }

    public function fieldTypes(): FieldTypeRegistry
    {
        return $this->types;
    }

    /**
     * @param  array<int, class-string<FieldType>|FieldType>  $types
     */
    public function registerFieldTypes(array $types): static
    {
        $this->types->register($types);

        return $this;
    }

    /**
     * @param  array<int, class-string<SubmissionSink>|SubmissionSink>  $sinks
     */
    public function sink(array|string|SubmissionSink $sinks): static
    {
        foreach (is_array($sinks) ? $sinks : [$sinks] as $sink) {
            $this->sinks[] = $sink;
        }

        return $this;
    }

    /**
     * @return array<int, SubmissionSink>
     */
    public function sinks(): array
    {
        return array_map(
            fn (string|SubmissionSink $sink): SubmissionSink => $sink instanceof SubmissionSink ? $sink : app($sink),
            [...(array) config('packstub-form-builder.sinks', []), ...$this->sinks],
        );
    }

    /**
     * Keep the "new submission" email to the form's notification addresses back for a form (an
     * order that is not paid yet: the email goes out once it is). The closure returns true to hold it.
     *
     * @param  \Closure(Form): bool  $check
     */
    public function holdNotificationsWhen(\Closure $check): static
    {
        $this->holdNotifications[] = $check;

        return $this;
    }

    public function notificationsHeld(Form $form): bool
    {
        foreach ($this->holdNotifications as $check) {
            if ($check($form)) {
                return true;
            }
        }

        return false;
    }

    public function forgetHooks(): static
    {
        $this->formTabs = $this->closedChecks = $this->beforeForm = $this->holdNotifications = [];
        $this->resourcePages = $this->recordActions = $this->submissionActions = [];

        return $this;
    }

    public function forgetSinks(): static
    {
        $this->sinks = [];

        return $this;
    }

    /**
     * Add a tab to the form editor in the panel (an app-specific settings area). The closure
     * returns a Filament Tab; the values it edits belong under the form's `settings`.
     *
     * @param  \Closure(): mixed  $factory
     */
    public function registerFormTab(\Closure $factory): static
    {
        $this->formTabs[] = $factory;

        return $this;
    }

    /** @return array<int, mixed> */
    public function formTabs(): array
    {
        return array_map(fn (\Closure $factory) => $factory(), $this->formTabs);
    }

    /**
     * Add a page of the app's own to the Forms resource (a report on one form...). The page is a
     * Filament resource page; `$path` is its route under the resource, e.g. "/{record}/insights".
     *
     * @param  class-string  $page
     */
    public function registerResourcePage(string $name, string $page, string $path): static
    {
        $this->resourcePages[$name] = ['page' => $page, 'path' => $path];

        return $this;
    }

    /**
     * @return array<string, array{page: class-string, path: string}>
     */
    public function resourcePages(): array
    {
        return $this->resourcePages;
    }

    /**
     * Add an action to each form: a row action of the forms table and a header action of the
     * form editor. The closure returns a Filament Action; its record is the form.
     *
     * @param  \Closure(): mixed  $factory
     */
    public function registerRecordAction(\Closure $factory): static
    {
        $this->recordActions[] = $factory;

        return $this;
    }

    /** @return array<int, mixed> */
    public function recordActions(): array
    {
        return array_map(fn (\Closure $factory) => $factory(), $this->recordActions);
    }

    /**
     * Add actions to a form's submissions page, into its menus: `export` (the "Export" button
     * above the table, next to the CSV), `record` (each row's menu) and `bulk` (the selected
     * rows' menu, BulkActions). The closure gets the form and the page, and returns any of the
     * three lists; the page groups them with its own.
     *
     * @param  \Closure(Form, HasTable): array{export?: array<int, mixed>, record?: array<int, mixed>, bulk?: array<int, mixed>}  $factory
     */
    public function registerSubmissionActions(\Closure $factory): static
    {
        $this->submissionActions[] = $factory;

        return $this;
    }

    /** @return array{export: array<int, mixed>, record: array<int, mixed>, bulk: array<int, mixed>} */
    public function submissionActions(Form $form, HasTable $page): array
    {
        $actions = ['export' => [], 'record' => [], 'bulk' => []];

        foreach ($this->submissionActions as $factory) {
            foreach ($factory($form, $page) as $slot => $list) {
                array_push($actions[$slot], ...$list);
            }
        }

        return $actions;
    }

    /**
     * Close a form for a reason of the app's own (a linked service that was switched off...).
     * The closure returns the message to show, or null to leave the form open.
     *
     * @param  \Closure(Form): ?string  $check
     */
    public function closedWhen(\Closure $check): static
    {
        $this->closedChecks[] = $check;

        return $this;
    }

    public function closedReasonFor(Form $form): ?string
    {
        foreach ($this->closedChecks as $check) {
            if (($reason = $check($form)) !== null) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * HTML shown above a form's fields (a price notice...). The closure returns markup that is
     * already escaped.
     *
     * @param  \Closure(Form): ?string  $renderer
     */
    public function beforeForm(\Closure $renderer): static
    {
        $this->beforeForm[] = $renderer;

        return $this;
    }

    public function beforeFormHtml(Form $form): string
    {
        return implode('', array_map(fn (\Closure $renderer): string => (string) $renderer($form), $this->beforeForm));
    }

    /**
     * Find a form by slug or id.
     */
    public function find(Form|string|int $form): ?Form
    {
        if ($form instanceof Form) {
            return $form;
        }

        $model = static::formModel();

        return is_int($form) || ctype_digit((string) $form)
            ? $model::query()->find($form)
            : $model::query()->where('slug', $form)->first();
    }

    /**
     * Submit from code, bypassing the spam checks that need a rendered form.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(Form|string|int $form, array $data, ?SubmissionContext $context = null): SubmissionResult
    {
        $form = $this->find($form) ?? throw new \InvalidArgumentException('Unknown form.');
        $context ??= new SubmissionContext(channel: 'code');

        return app(Submitter::class)->submit($form, $data, $context->trusted());
    }
}
