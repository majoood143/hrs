<?php

namespace App\Models;

use App\Support\Silks\SilkSvgSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A pattern for one area of the silks (body, sleeves or cap): SVG shapes in that area's local box
 * (SilksTemplate::BOXES), painted in the visitor's pattern colour. Always stored sanitized.
 */
class SilkPattern extends Model
{
    use LogsActivity;

    protected $fillable = ['area', 'key', 'en_name', 'ar_name', 'svg', 'is_active', 'sort'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['area', 'key', 'en_name', 'ar_name', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('SilkPattern');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort')->orderBy('id');
    }

    public function localizedName(): string
    {
        return (app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name) ?: $this->en_name;
    }

    protected function setSvgAttribute(?string $value): void
    {
        $this->attributes['svg'] = SilkSvgSanitizer::clean($value);
    }
}
