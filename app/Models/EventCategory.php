<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCategory extends Model
{
    protected $fillable = ['en_name', 'ar_name', 'color', 'order'];

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category_id');
    }
}
