<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A breeding stallion standing in Oman (admin → Directory → Stallions; first seeded from the 2025
 * catalogue of the horse owners and breeders association, StallionSeeder). The pedigree is a
 * 3-generation tree and/or an outside link; the live race history is the racing club's horse
 * profile (`racing_horse_id`, read through RacingClient), the catalogue's record stays as numbers.
 */
class Stallion extends Model
{
    public const COLOURS = ['grey', 'bay', 'chestnut', 'dark_chestnut', 'black'];

    /** The ancestors behind the sire and dam, in the order a pedigree is read (s = sire, d = dam). */
    public const PEDIGREE_KEYS = [
        'sire_sire', 'sire_dam', 'dam_sire', 'dam_dam',
        'sire_sire_sire', 'sire_sire_dam', 'sire_dam_sire', 'sire_dam_dam',
        'dam_sire_sire', 'dam_sire_dam', 'dam_dam_sire', 'dam_dam_dam',
    ];

    protected $fillable = [
        'en_name', 'ar_name', 'origin', 'colour', 'foaled_year',
        'sire_en', 'sire_ar', 'dam_en', 'dam_ar', 'pedigree', 'pedigree_url',
        'runs', 'wins', 'places', 'rating', 'racing_horse_id', 'highlights_en', 'highlights_ar',
        'stud_en', 'stud_ar', 'city_id', 'contact_number', 'cover_photo', 'is_active',
    ];

    protected $casts = [
        'pedigree' => 'array',
        'foaled_year' => 'integer',
        'runs' => 'integer',
        'wins' => 'integer',
        'places' => 'integer',
        'rating' => 'integer',
        'racing_horse_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return array<string, string> the colours in the current language */
    public static function colourOptions(): array
    {
        return collect(self::COLOURS)->mapWithKeys(fn (string $colour) => [$colour => __('stallions.colours.'.$colour)])->all();
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param  array<string, mixed>  $filters  `q` (name, sire or dam, either language), `colour` */
    public function scopeFilteredBy(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['colour'] ?? null, fn ($q, $v) => $q->where('colour', $v))
            ->when(trim((string) ($filters['q'] ?? '')), function ($q, string $term) {
                $like = '%'.$term.'%';
                $q->where(fn ($w) => $w->where('en_name', 'like', $like)->orWhere('ar_name', 'like', $like)
                    ->orWhere('sire_en', 'like', $like)->orWhere('sire_ar', 'like', $like)
                    ->orWhere('dam_en', 'like', $like)->orWhere('dam_ar', 'like', $like));
            });
    }

    /** Alphabetical in the current language. */
    public function scopeByName(Builder $query): Builder
    {
        return $query->orderBy(app()->getLocale() === 'ar' ? 'ar_name' : 'en_name');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function getSireAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->sire_ar : null) ?: $this->sire_en;
    }

    public function getDamAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->dam_ar : null) ?: $this->dam_en;
    }

    public function getHighlightsAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->highlights_ar : $this->highlights_en) ?: null;
    }

    public function getStudAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->stud_ar : null) ?: $this->stud_en;
    }

    public function getColourLabelAttribute(): ?string
    {
        return $this->colour ? (self::colourOptions()[$this->colour] ?? null) : null;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->cover_photo ? Storage::disk('public')->url($this->cover_photo) : null;
    }

    /**
     * The whole tree in reading order, the sire and dam in the current language and the rest as stored.
     *
     * @return array<string, string|null>
     */
    public function pedigreeTree(): array
    {
        $stored = (array) ($this->pedigree ?? []);

        return ['sire' => $this->sire, 'dam' => $this->dam]
            + collect(self::PEDIGREE_KEYS)->mapWithKeys(fn (string $key) => [$key => ($stored[$key] ?? null) ?: null])->all();
    }
}
