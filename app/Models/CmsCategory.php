<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class CmsCategory extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'slug', 'description', 'parent_id', 'order'];

    public array $translatable = ['name', 'description'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CmsCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CmsCategory::class, 'parent_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CmsPost::class, 'category_id');
    }

    public function canBeDeleted(): bool
    {
        return $this->posts()->doesntExist();
    }
}
