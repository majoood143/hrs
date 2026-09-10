<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsMenu extends Model
{
    protected $fillable = ['name', 'slug', 'location'];

    public function items(): HasMany
    {
        return $this->hasMany(CmsMenuItem::class, 'menu_id')->whereNull('parent_id')->orderBy('order');
    }

    public function allItems(): HasMany
    {
        return $this->hasMany(CmsMenuItem::class, 'menu_id')->orderBy('order');
    }
}
