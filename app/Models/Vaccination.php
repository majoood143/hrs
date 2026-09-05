<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vaccination extends Model
{
    //
    protected $fillable = ['vaccine_type','date_administered'];
    
}
