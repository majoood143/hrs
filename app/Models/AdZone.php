<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class AdZone extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'slug', 'order', 'is_active'];

    public array $translatable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class, 'zone_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
