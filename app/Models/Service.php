<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name',
        'ar_name',
        'description',
        'ar_description',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    /**
     * The name in the visitor's language. `name` itself stays the English value (it is
     * what the admin form edits), so the localized one is a method, not an accessor.
     */
    public function localizedName(): string
    {
        return (app()->getLocale() === 'ar' ? $this->ar_name : null) ?: (string) $this->name;
    }

    public function localizedDescription(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->ar_description : null) ?: $this->description;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }
}
