<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One kind of expense (Feed, Vet, Rent…), kept by the admins; `scope` = both | platform | stable. */
class ExpenseCategory extends Model
{
    public const SCOPES = ['both', 'platform', 'stable'];

    protected $fillable = ['en_name', 'ar_name', 'scope', 'is_active', 'sort'];

    protected $attributes = ['scope' => 'both', 'is_active' => true, 'sort' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort' => 'integer'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    /** Active ones offered to stables (forStables) or to us. */
    public function scopeOffered(Builder $query, bool $forStables): Builder
    {
        return $query->where('is_active', true)
            ->whereIn('scope', ['both', $forStables ? 'stable' : 'platform'])
            ->orderBy('sort')->orderBy('id');
    }

    /** @return array<int, string> id => name in the current language */
    public static function options(bool $forStables): array
    {
        return static::query()->offered($forStables)->get()->mapWithKeys(fn (self $c) => [$c->getKey() => $c->name])->all();
    }
}
