<?php

namespace App\Models;

use App\Models\Concerns\RefusesDeleteWhileReferenced;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;
    use RefusesDeleteWhileReferenced;

    /** What refers to a city: `RefusesDeleteWhileReferenced` refuses to delete one still in use. */
    public const DELETE_REFERENCES = [
        'stables' => 'city_id',
        'shops' => 'city_id',
        'clinics' => 'city_id',
        'centers' => 'city_id',
        'farriers' => 'city_id',
        'horses' => 'city_id',
        'horse_sale_posts' => 'city_id',
        'tool_sale_posts' => 'city_id',
        'transfer_posts' => ['from_city_id', 'to_city_id'],
    ];

    protected $fillable = ['region_id', 'en_name', 'ar_name'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function horse(): HasMany
    {
        return $this->hasMany(Horse::class);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }
}
