<?php

namespace Packstub\FormBuilder\Submissions;

use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

final class SubmissionResult
{
    /**
     * @param  array<string, mixed>  $extra  merged into the JSON response
     */
    public function __construct(
        public readonly Form $form,
        public readonly ?FormSubmission $submission,
        public readonly bool $spam = false,
        public readonly ?string $redirect = null,
        public readonly ?string $customMessage = null,
        public readonly array $extra = [],
    ) {}

    public function message(): string
    {
        return $this->customMessage ?? $this->form->successMessage();
    }

    /** Where to send the visitor: what a listener asked for, else the form's own redirect. */
    public function redirectUrl(): ?string
    {
        return $this->redirect ?? ($this->form->redirect_url ?: null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => true,
            'message' => $this->message(),
            'redirect' => $this->redirectUrl(),
            'id' => $this->submission?->getKey(),
            ...$this->extra,
        ];
    }
}
