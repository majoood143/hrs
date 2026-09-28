<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    public const TYPE_STABLE_OWNER = 'stable_owner';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'civiled_id',
        'cr_number',
        'phone',
        'is_admin',
        'type',
        'country_id',
        'region_id',
        'city_id',
        'postal_code',
        'locale',
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
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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

    /** The stables this user runs from the /stable panel (as owner or staff). */
    public function stables(): BelongsToMany
    {
        return $this->belongsToMany(Stable::class, 'stable_user')->withPivot('role')->withTimestamps();
    }

    public function isStableOwner(): bool
    {
        return $this->type === self::TYPE_STABLE_OWNER;
    }

    /** @var bool|null worked out once per request */
    private ?bool $managesStables = null;

    /**
     * An admin who may work in any stable's panel on its owner's behalf: a super admin, or anyone
     * with the Shield permission to edit stables. Never a stable owner.
     */
    public function managesStables(): bool
    {
        if ($this->isStableOwner()) {
            return false;
        }

        return $this->managesStables ??= $this->hasRole((string) config('filament-shield.super_admin.name', 'super_admin'))
            || $this->checkPermissionTo('Update:Stable');
    }

    /**
     * Stable owners use the /stable panel only. Everyone else keeps the admin panel as before (what
     * they may do there is still decided by their Shield permissions).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => ! $this->isStableOwner(),
            'stable' => $this->isStableOwner() || $this->managesStables(),
            default => true,
        };
    }

    /** @return Collection<int, Stable> */
    /** An owner's own stables; for an admin who manages stables, every stable (the menu is searchable). */
    public function getTenants(Panel $panel): Collection
    {
        return $this->isStableOwner()
            ? $this->stables()->orderBy('stables.id')->get()
            : ($this->managesStables() ? Stable::query()->orderBy('en_name')->get() : new Collection);
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Stable && ($this->belongsToStable($tenant->getKey()) || $this->managesStables());
    }

    public function belongsToStable(int|string|null $stableId): bool
    {
        if ($stableId === null) {
            return false;
        }

        if ($this->relationLoaded('stables')) {
            return $this->stables->contains('id', (int) $stableId);
        }

        return $this->stables()->whereKey($stableId)->exists();
    }
}
