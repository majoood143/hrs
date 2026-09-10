<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    //
    use HasFactory;

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
