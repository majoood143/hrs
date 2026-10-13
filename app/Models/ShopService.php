<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ShopService extends Model
{
    public const APPLIES_TO = [
        'all' => 'All Types',
        'tack' => 'Tack',
        'feed_supplements' => 'Feed & Supplements',
        'equipment' => 'Equipment',
        'saddlery' => 'Saddlery',
    ];

    protected $fillable = ['en_name', 'ar_name', 'applies_to'];

    /** @return array<string, string> APPLIES_TO in the current language */
    public static function appliesToOptions(): array
    {
        return collect(self::APPLIES_TO)
            ->mapWithKeys(fn ($label, string $key) => [$key => $key === 'all' ? __('admin_shop_service.applies_to.all') : __('shops.types.'.$key)])
            ->all();
    }

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
