<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A phone's saved filters on a board: new posts matching them are pushed to it (App\Jobs\NotifySavedSearches). */
class PushSavedSearch extends Model
{
    /** Board => [the post model (with visible() and filteredBy()), the filters it keeps]. */
    public const SECTIONS = [
        'horses' => [HorseSalePost::class, ['type_id', 'gender_id', 'country_id', 'region_id', 'city_id', 'breed', 'price_max']],
        'tools' => [ToolSalePost::class, ['category', 'condition', 'country_id', 'region_id', 'city_id', 'price_max']],
    ];

    public const MAX_PER_PHONE = 10;

    protected $fillable = ['mobile_device_id', 'section', 'filters'];

    protected $casts = ['filters' => 'array'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(MobileDevice::class, 'mobile_device_id');
    }

    /**
     * The phones with a search on this board that this visible post matches, once each.
     *
     * @return list<int>
     */
    public static function deviceIdsFor(string $section, int $postId): array
    {
        if (! isset(self::SECTIONS[$section])) {
            return [];
        }

        [$model] = self::SECTIONS[$section];

        // shortcut: one query per saved search; group searches by filters if there are thousands
        return self::query()->where('section', $section)->get()
            ->filter(fn (self $search) => $model::query()->visible()->whereKey($postId)->filteredBy($search->filters)->exists())
            ->pluck('mobile_device_id')
            ->unique()
            ->values()
            ->all();
    }

    /** "Arabian · Mare · Muscat · up to 5,000.000", in the current language. */
    public function label(): string
    {
        $f = $this->filters;
        $name = fn (string $model, string $key) => isset($f[$key]) ? $model::query()->find((int) $f[$key])?->name : null;

        $parts = $this->section === 'horses'
            ? [$name(Type::class, 'type_id'), $name(Gender::class, 'gender_id'), $f['breed'] ?? null]
            : [
                isset($f['category']) ? __('tools-for-sale.categories.'.$f['category']) : null,
                isset($f['condition']) ? __('tools-for-sale.conditions.'.$f['condition']) : null,
            ];

        $parts[] = $name(City::class, 'city_id') ?? $name(Region::class, 'region_id') ?? $name(Country::class, 'country_id');
        $parts[] = isset($f['price_max']) ? __('api.push.up_to', ['price' => SiteSetting::formatCurrency((float) $f['price_max'])]) : null;

        return implode(' · ', array_filter($parts)) ?: __('api.push.all_'.$this->section);
    }
}
