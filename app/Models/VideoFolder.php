<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class VideoFolder extends Model
{
    use HasTranslations;

    protected $fillable = ['parent_id', 'name', 'slug', 'order', 'is_active'];

    public array $translatable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'folder_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderByDesc('id');
    }

    /**
     * Total active videos in this folder plus every descendant folder,
     * recursively. Cheap enough at catalog scale (a handful of folders).
     */
    public function activeVideosCount(): int
    {
        return $this->videos()->active()->count()
            + $this->children()->active()->get()
                ->sum(fn (self $child) => $child->activeVideosCount());
    }

    /**
     * IDs of this folder and all of its descendants, used to keep a folder
     * (or one of its own subfolders) out of its "parent" select options.
     *
     * @return array<int, int>
     */
    public function selfAndDescendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children()->get() as $child) {
            $ids = array_merge($ids, $child->selfAndDescendantIds());
        }

        return $ids;
    }

    /**
     * Full breadcrumb from the top-level ancestor down to this folder, e.g.
     * "Main Folder - Sub Folder", so admins can tell apart folders that
     * share a name under different parents.
     */
    public function getPathLabelAttribute(): string
    {
        return $this->parent ? $this->parent->path_label . ' - ' . $this->name : $this->name;
    }

    /**
     * The top-level ancestor of this folder (itself, when it has no parent).
     */
    public function rootAncestor(): self
    {
        return $this->parent ? $this->parent->rootAncestor() : $this;
    }

    /**
     * Breadcrumb of subfolder names below the top-level ancestor, e.g.
     * "Sub Folder - Sub Sub Folder", or null when this folder is itself
     * top-level.
     */
    public function getSubPathLabelAttribute(): ?string
    {
        if (! $this->parent) {
            return null;
        }

        $names = [];
        $node = $this;

        while ($node->parent) {
            array_unshift($names, $node->name);
            $node = $node->parent;
        }

        return implode(' - ', $names);
    }
}
