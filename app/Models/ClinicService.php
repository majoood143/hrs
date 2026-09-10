<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicService extends Model
{
    protected $fillable = ['en_name', 'ar_name'];

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }
}
