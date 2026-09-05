<?php

namespace App\Models;

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
    protected $fillable = ['en_name','ar_name'];

    public function region():HasMany
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
    {
        return $this->hasMany(Region::class);
    }
}
