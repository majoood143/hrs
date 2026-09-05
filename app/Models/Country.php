<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    //
    protected $fillable = ['en_name','ar_name'];

    public function region():HasMany
    {
        return $this->hasMany(Region::class);
    }
}
