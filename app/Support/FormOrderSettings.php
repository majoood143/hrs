<?php

namespace App\Support;

use App\Models\Service;
use App\Models\ServiceOrder;
use App\Services\Orders\OrderPricing;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

/**
 * What a form's "Service & payment" tab says, kept in the form's `settings` JSON:
 *
 *   payment.service_id           the service this form orders (no service: a plain form)
 *   customer.{name,email,phone}_field   which of the form's fields identify the customer
 *   notifications.{email,sms}    tell the customer by email / SMS
 */
class FormOrderSettings
{
    private ?Service $service = null;

    private bool $serviceLoaded = false;

    public function __construct(private readonly Form $form) {}

    public static function for(Form $form): self
    {
        return new self($form);
    }

    public function serviceId(): ?int
    {
        $id = $this->form->setting('payment.service_id');

        return filled($id) ? (int) $id : null;
    }

    public function hasService(): bool
    {
        return $this->serviceId() !== null;
    }

    public function service(): ?Service
    {
        if (! $this->serviceLoaded) {
            $this->service = $this->serviceId() ? Service::find($this->serviceId()) : null;
            $this->serviceLoaded = true;
        }

        return $this->service;
    }

    /**
     * Whether the form's "new submission" email waits for payment: the form orders a service that
     * costs something, so the request is not work for the reviewers until it is paid.
     */
    public function heldUntilPaid(): bool
    {
        $service = $this->service();

        return $service !== null && (float) $service->price > 0;
    }

    /**
     * The review stages, in order: each has a name (per language) and the role whose holders may decide it.
     * A form with none skips review (an admin completes its orders directly).
     *
     * @return list<array{name: array<string, string>, role: string}>
     */
    public function stages(): array
    {
        return collect((array) $this->form->setting('approval.stages', []))
            ->filter(fn ($stage) => is_array($stage) && filled($stage['role'] ?? null))
            ->map(fn (array $stage) => ['name' => array_filter((array) ($stage['name'] ?? []), 'filled') ?: ['en' => (string) $stage['role']], 'role' => (string) $stage['role']])
            ->values()
            ->all();
    }

    /** Whether an order of this form can only be completed once a result document has been uploaded. */
    public function requiresDocument(): bool
    {
        return (bool) $this->form->setting('approval.requires_document', false);
    }

    public function notifiesByEmail(): bool
    {
        return (bool) $this->form->setting('notifications.email', true);
    }

    public function notifiesBySms(): bool
    {
        return (bool) $this->form->setting('notifications.sms', false);
    }

    /**
     * The key of the field that holds the customer's name / email / phone: the one picked in the
     * tab if it still exists, else the first field of the matching kind.
     */
    public function fieldKey(string $role): ?string
    {
        $chosen = $this->form->setting("customer.{$role}_field");
        $fields = $this->form->inputFields();

        if (filled($chosen) && $fields->has($chosen)) {
            return $chosen;
        }

        return match ($role) {
            'email' => $fields->first(fn (Field $field) => $field->type::id() === 'email')?->key,
            'phone' => $fields->first(fn (Field $field) => $field->type::id() === 'phone')?->key,
            'name' => ($fields->first(fn (Field $field) => $field->type::id() === 'text' && str_contains($field->key, 'name'))
                ?? $fields->first(fn (Field $field) => $field->type::id() === 'text'))?->key,
            default => null,
        };
    }

    /**
     * The customer as the order stores them.
     *
     * @return array{name: ?string, email: ?string, phone: ?string, locale: string, customer_id?: ?int}
     */
    public function customer(FormSubmission $submission): array
    {
        $value = function (string $role) use ($submission): ?string {
            $key = $this->fieldKey($role);
            $raw = $key ? $submission->value($key) : null;

            return is_scalar($raw) && trim((string) $raw) !== '' ? trim((string) $raw) : null;
        };

        return [
            'name' => $value('name'),
            'email' => $value('email'),
            'phone' => PhoneNumber::normalize($value('phone')),
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * What the customer will pay, shown above the form. An unsaved order carries the quote so
     * the same breakdown view serves the checkout page, the order page and this notice.
     */
    public function priceNoticeHtml(): string
    {
        $service = $this->service();

        if (! $service || ! $service->is_active) {
            return '';
        }

        $quote = app(OrderPricing::class)->quote($service, $this->form->getKey());
        $order = new ServiceOrder($quote->toOrderAttributes());
        $order->setRelation('service', $service);

        return view('site.orders._price-notice', ['order' => $order, 'free' => $quote->isFree()])->render();
    }

    /**
     * The fields of a form as the editor holds them right now (unsaved), as key => label, for
     * the tab's pickers.
     *
     * @param  array<int|string, mixed>|null  $builderState  the "fields" state of the editor
     * @param  list<string>  $types  field type ids to keep
     * @return array<string, string>
     */
    public static function fieldOptions(?array $builderState, array $types): array
    {
        $registry = app(FieldTypeRegistry::class);

        return collect($builderState ?? [])
            ->map(fn ($item) => is_array($item) ? Field::fromArray($item, $registry) : null)
            ->filter(fn (?Field $field) => $field !== null && $field->isInput() && in_array($field->type::id(), $types, true))
            ->mapWithKeys(fn (Field $field) => [$field->key => $field->label.' ('.$field->key.')'])
            ->all();
    }
}
