<?php

namespace App\Models;

use App\Models\Concerns\RefusesDeleteWhileReferenced;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Region extends Model
{
    use HasFactory;
    use RefusesDeleteWhileReferenced;

    /** What refers to a region: `RefusesDeleteWhileReferenced` refuses to delete one still in use. */
    public const DELETE_REFERENCES = [
        'cities' => 'region_id',
        'stables' => 'region_id',
        'shops' => 'region_id',
        'clinics' => 'region_id',
        'centers' => 'region_id',
        'farriers' => 'region_id',
        'horses' => 'region_id',
        'horse_sale_posts' => 'region_id',
        'tool_sale_posts' => 'region_id',
        'transfer_posts' => ['from_region_id', 'to_region_id'],
    ];

    protected $fillable = ['country_id', 'en_name', 'ar_name'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->hasMany(City::class, 'region_id');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }
}
