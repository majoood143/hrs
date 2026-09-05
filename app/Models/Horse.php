<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Horse extends Model
{
    //
    protected $casts = [
        'attachment' => 'array',
        'dob' => 'date',
    ];

    protected $fillable = ['en_name', 'ar_name', 'country_id', 'region_id', 'city_id', 'type_id', 'gender_id', 'user_id', 'dob', 'color_id', 'breed', 'microchip', 'registration_number', 'dam_id', 'sire_id'];


    use HasFactory;

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->dob ? Carbon::parse($this->dob)->age : null,
        );
    }

    public function attachement(): HasMany
    {
        return $this->hasMany(Attachement::class);
    }

    public function vaccination(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function transaction(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function service(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
