<?php

namespace Packstub\FormBuilder\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Submissions\SubmissionContext;

/**
 * An accepted submission. The submission is saved unless the form keeps
 * "store submissions" off; either way $submission->form is loaded.
 *
 * A listener may steer what the visitor sees next: redirectTo() sends them somewhere
 * (a payment page, an order page) instead of the form's own redirect or success message,
 * and $extra is merged into the JSON response.
 */
class SubmissionReceived
{
    use Dispatchable;

    public ?string $redirectUrl = null;

    public ?string $message = null;

    /** @var array<string, mixed> */
    public array $extra = [];

    public function __construct(
        public readonly Form $form,
        public readonly FormSubmission $submission,
        public readonly SubmissionContext $context,
    ) {}

    public function redirectTo(string $url, ?string $message = null): static
    {
        $this->redirectUrl = $url;
        $this->message = $message ?? $this->message;

        return $this;
    }
}
