<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;

/**
 * One line of an order's timeline. Public events are shown to the customer.
 */
class OrderEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'is_public' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The line as the viewer should read it. `message` was saved in the language of whoever caused
     * the event (an admin's English, say), so a type with a text in lang/{en,ar}/orders.php is always
     * shown from there, in the current locale; other types (a stage name) keep their saved message.
     */
    public function label(): string
    {
        $key = 'orders.events.'.$this->type;

        return Lang::has($key) ? __($key) : (string) ($this->message ?: $this->type);
    }

    /** A heroicon name for the customer timeline. */
    public function icon(): string
    {
        return match ($this->type) {
            'created' => 'heroicon-o-document-plus',
            'paid' => 'heroicon-o-banknotes',
            'payment_started' => 'heroicon-o-credit-card',
            'payment_failed' => 'heroicon-o-exclamation-triangle',
            'cancelled', 'rejected' => 'heroicon-o-x-circle',
            'review_started' => 'heroicon-o-magnifying-glass',
            'approved', 'stage_approved' => 'heroicon-o-check-badge',
            'completed' => 'heroicon-o-check-circle',
            'refunded' => 'heroicon-o-arrow-uturn-left',
            'document_added', 'document_replaced', 'document_removed' => 'heroicon-o-paper-clip',
            default => 'heroicon-o-clock',
        };
    }
}
