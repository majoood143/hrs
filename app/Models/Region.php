<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    public function country()
    {
        return $this->belongsTo(HorseCountry::class);
    }
}
