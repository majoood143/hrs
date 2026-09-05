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

>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
}
