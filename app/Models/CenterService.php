<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CenterService extends Model
{
    public const APPLIES_TO = [
        'all' => 'All Types',
        'training' => 'Training',
        'breeding' => 'Breeding',
        'boarding' => 'Boarding',
        'rehabilitation' => 'Rehabilitation',
    ];

    protected $fillable = ['en_name', 'ar_name', 'applies_to'];

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function scopeForType(Builder $query, ?string $type): Builder
    {
        return $query->when(
            $type,
            fn (Builder $q) => $q->whereIn('applies_to', [$type, 'all'])
        );
    }
}
