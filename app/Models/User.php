<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
<<<<<<< HEAD
=======
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)

class User extends Authenticatable
{
<<<<<<< HEAD
    /** @use HasFactory<\Database\Factories\UserFactory> */
<<<<<<< HEAD
    use HasFactory, Notifiable;
=======
=======
    /** @use HasFactory<UserFactory> */
>>>>>>> 8bf96602 (Run filament-v4 automated codemod (namespace migrations))
    use HasFactory, Notifiable,HasRoles;
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
<<<<<<< HEAD
=======
        'civiled_id',
        'cr_number',
        'phone',
        'is_admin',
        'type',
        //'country_id',
        'region',
        'city',
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
<<<<<<< HEAD
=======

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
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
}
