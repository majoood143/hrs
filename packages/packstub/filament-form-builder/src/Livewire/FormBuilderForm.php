<?php

namespace Packstub\FormBuilder\Livewire;

use Filament\Forms\Components\Field as FilamentField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Packstub\FormBuilder\Exceptions\FormClosedException;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Models\Form as FormModel;
use Packstub\FormBuilder\Submissions\ProtectionToken;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;
use Packstub\FormBuilder\Support\FieldConditions;

/**
 * <livewire:form-builder form="contact" />
 *
 * The Livewire renderer: the form's fields as Filament components, validated
 * in place, submitted through the same pipeline as the plain renderer. The
 * page needs Filament's frontend assets (@filamentStyles / @filamentScripts).
 */
class FormBuilderForm extends Component implements HasForms
{
    use InteractsWithForms;

    #[Locked]
    public int $formId;

    #[Locked]
    public string $token = '';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public bool $submitted = false;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(FormModel|string|int $form): void
    {
        $model = FormBuilder::find($form) ?? abort(404);

        $this->formId = (int) $model->getKey();
        $this->token = app(ProtectionToken::class)->make($model);
        $this->error = $model->closedReason();
        $this->form->fill();
    }

    public function getFormModel(): FormModel
    {
        return FormBuilder::formModel()::query()->findOrFail($this->formId);
    }

    public function form(Schema $schema): Schema
    {
        $model = $this->getFormModel();
        $sources = $model->fieldList()->map(fn (Field $field): ?string => $field->condition()['field'] ?? null)->filter()->all();

        return $schema
            ->components([
                Grid::make(2)->schema(
                    $model->fieldList()
                        ->map(function (Field $field) use ($model, $sources): SchemaComponent {
                            $component = $field->type->formComponent($field);

                            // a field others depend on re-renders the form as it changes
                            if (in_array($field->key, $sources, true) && $component instanceof FilamentField) {
                                $component->live();
                            }

                            // hidden components are neither validated nor sent, as the server expects
                            if ($field->condition() !== null) {
                                $component->visible(fn (): bool => in_array($field->key, FieldConditions::visibleKeys($model, (array) $this->data), true));
                            }

                            return $component;
                        })
                        ->all(),
                ),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $model = $this->getFormModel();
        $state = $this->form->getState();

        $context = SubmissionContext::fromRequest(request(), 'livewire');

        try {
            $result = app(Submitter::class)->submit($model, [
                ...$state,
                app(ProtectionToken::class)->field() => $this->token,
            ], $context);
        } catch (FormClosedException $e) {
            $this->error = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('data.'.$key, $messages[0]);
            }

            return;
        }

        if ($result->redirectUrl() !== null) {
            $this->redirect($result->redirectUrl());

            return;
        }

        $this->submitted = true;
        $this->message = $result->message();
        $this->form->fill();
    }

    public function render(): View
    {
        return view('packstub-form-builder::livewire.form', [
            'model' => $this->getFormModel(),
        ]);
    }
}
