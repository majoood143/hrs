<?php

namespace App\Models;

<<<<<<< HEAD
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    public function country()
    {
        return $this->belongsTo(HorseCountry::class);
    }
=======
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Region extends Model
{
    //
    use HasFactory;

        protected $fillable = ['country_id','en_name','ar_name'];

        public function country(): BelongsTo
        {
            return $this->belongsTo(Country::class);
        }

        public function city()
        {
            return $this->hasMany(City::class,'region_id');
        }

<<<<<<< HEAD
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
=======
        public function getNameAttribute(): string
        {
            return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
        }

>>>>>>> bbd33618 (Add transfer board creation and listing views)
}
