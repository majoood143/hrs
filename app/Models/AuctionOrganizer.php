<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Who runs an auction (a stud, a club): its name, logo and colour head the auction's pages. None = the site itself. */
class AuctionOrganizer extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['team' => 'array'];

    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class, 'organizer_id');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function getAboutAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->ar_about : $this->en_about) ?: null;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }
}
