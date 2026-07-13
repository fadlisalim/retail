<?php

namespace App\Models\Concerns;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Lightweight RBAC for the User model: roles (many-to-many) whose permissions
 * are unioned. Super-admin short-circuits every check.
 */
trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /** All permission slugs granted through any of the user's roles. */
    public function permissions(): Collection
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissions()->contains($slug);
    }

    /** True if the user holds any back-office role at all. */
    public function isStaffMember(): bool
    {
        return (bool) $this->is_staff || $this->roles->isNotEmpty();
    }

    public function assignRole(string|Role $role): void
    {
        $role = $role instanceof Role ? $role : Role::where('slug', $role)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->id]);
        $this->unsetRelation('roles');
    }
}
