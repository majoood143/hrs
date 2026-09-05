<?php

namespace App\Models;

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

}
