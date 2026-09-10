<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

<<<<<<< HEAD
class Country extends Model
{
    public function regions()
=======
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    //
    protected $fillable = ['en_name','ar_name','is_public'];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function region():HasMany
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
    {
        return $this->hasMany(Region::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }
}
