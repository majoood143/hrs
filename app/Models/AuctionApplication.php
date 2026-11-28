<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A seller's request to put a horse in an auction (/auctions/sell), until an admin approves it into a lot or rejects it. */
class AuctionApplication extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    /** Waiting applications a customer may have at once. */
    public const MAX_PENDING = 5;

    protected $guarded = ['id'];

    protected $casts = [
        'born_on' => 'date',
        'asking_reserve' => 'decimal:3',
        'photos' => 'array',
        'videos' => 'array',
        'documents' => 'array',
        'declared_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AuctionLot::class, 'auction_lot_id');
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return collect(self::STATUSES)->mapWithKeys(fn (string $s) => [$s => __('auctions.application_statuses.'.$s)])->all();
    }

    /** @return array<int, string> */
    public function photoUrls(): array
    {
        return collect($this->photos ?? [])->map(fn (string $path) => Storage::disk('public')->url($path))->all();
    }
}
