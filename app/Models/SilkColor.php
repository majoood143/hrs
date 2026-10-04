<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** A colour visitors can pick in the racing silks designer. */
class SilkColor extends Model
{
    use LogsActivity;

    protected $fillable = ['key', 'en_name', 'ar_name', 'hex', 'is_active', 'sort'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'en_name', 'ar_name', 'hex', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('SilkColor');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort')->orderBy('id');
    }

    public function localizedName(): string
    {
        return (app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name) ?: $this->en_name;
    }

    protected function setHexAttribute(?string $value): void
    {
        $this->attributes['hex'] = strtoupper((string) $value);
    }
}
