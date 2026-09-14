<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class CmsMenuItem extends Model
{
    use HasTranslations;

    protected $fillable = ['menu_id', 'parent_id', 'page_id', 'label', 'url', 'route_name', 'target', 'is_button', 'order'];

    protected $casts = [
        'is_button' => 'boolean',
    ];

    public array $translatable = ['label'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(CmsMenu::class, 'menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CmsMenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CmsMenuItem::class, 'parent_id')->orderBy('order');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'page_id');
    }

    public function resolvedUrl(): string
    {
        if ($this->page) {
            return $this->page->is_homepage ? '/' : '/' . $this->page->slug;
        }

        if ($this->route_name && \Illuminate\Support\Facades\Route::has($this->route_name)) {
            return route($this->route_name);
        }

        return $this->url ?? '#';
    }

    public static function siteSectionOptions(): array
    {
        return [
            'home' => __('Home'),
            'blog.index' => __('Stories'),
            'stables.index' => __('stables.nav_label'),
            'clinics.index' => __('clinics.nav_label'),
            'centers.index' => __('centers.nav_label'),
            'shops.index' => __('shops.nav_label'),
            'horses-for-sale.index' => __('horses-for-sale.nav_label'),
            'farriers.index' => __('farriers.nav_label'),
            'tools-for-sale.index' => __('tools-for-sale.nav_label'),
            'transfer-board.index' => __('Find a Transfer'),
            'events.index' => __('events.nav_label'),
        ];
    }
}
