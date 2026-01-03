<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;

/**
 * Trait for models that can belong to multiple tenants.
 *
 * @mixin Model
 */
trait HasTenants
{
    /**
     * Boot the HasTenants trait.
     */
    public static function bootHasTenants(): void
    {
        static::deleting(function (Model $model) {
            if (method_exists($model, 'tenants')) {
                $model->tenants()->detach();
            }
        });
    }

    /**
     * Get all tenants this model belongs to.
     *
     * @return MorphToMany<Tenant>
     */
    public function tenants(): MorphToMany
    {
        $tenantModel = config('laratenauth.tenant_model', Tenant::class);
        $pivotTable = config('laratenauth.database.tenant_user_table', 'tenant_user');

        return $this->morphToMany($tenantModel, 'tenant_userable', $pivotTable)
            ->withPivot(['role', 'permissions', 'is_default'])
            ->withTimestamps();
    }

    /**
     * Get the default tenant for this model.
     */
    public function defaultTenant(): ?Tenant
    {
        return $this->tenants()
            ->wherePivot('is_default', true)
            ->first();
    }

    /**
     * Check if this model belongs to a specific tenant.
     */
    public function belongsToTenant(int|string|Tenant $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $tenantsTable = config('laratenauth.database.tenants_table', 'tenants');

        return $this->tenants()->where("{$tenantsTable}.id", $tenantId)->exists();
    }

    /**
     * Check if this model has access to a specific tenant.
     * Alias for belongsToTenant with additional permission checking.
     */
    public function hasAccessToTenant(int|string|Tenant $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $tenantsTable = config('laratenauth.database.tenants_table', 'tenants');

        $pivotRecord = $this->tenants()
            ->where("{$tenantsTable}.id", $tenantId)
            ->first();

        if (! $pivotRecord) {
            return false;
        }

        // Additional checks can be added here (e.g., role-based access)
        return true;
    }

    /**
     * Add this model to a tenant.
     */
    public function joinTenant(
        int|string|Tenant $tenant,
        ?string $role = null,
        array $permissions = [],
        bool $isDefault = false
    ): void {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $tenantsTable = config('laratenauth.database.tenants_table', 'tenants');

        // If this should be the default tenant, remove default from others
        if ($isDefault) {
            $this->tenants()->updateExistingPivot(
                $this->tenants()->pluck("{$tenantsTable}.id")->toArray(),
                ['is_default' => false]
            );
        }

        $this->tenants()->attach($tenantId, [
            'role' => $role,
            'permissions' => json_encode($permissions),
            'is_default' => $isDefault,
        ]);
    }

    /**
     * Attach this model to a tenant.
     * Alias for joinTenant().
     */
    public function attachToTenant(
        int|string|Tenant $tenant,
        ?string $role = null,
        array $permissions = [],
        bool $isDefault = false
    ): void {
        $this->joinTenant($tenant, $role, $permissions, $isDefault);
    }

    /**
     * Remove this model from a tenant.
     */
    public function leaveTenant(int|string|Tenant $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        $this->tenants()->detach($tenantId);
    }

    /**
     * Set the default tenant for this model.
     */
    public function setDefaultTenant(int|string|Tenant $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $tenantsTable = config('laratenauth.database.tenants_table', 'tenants');

        // Remove default from all tenants
        $this->tenants()->updateExistingPivot(
            $this->tenants()->pluck("{$tenantsTable}.id")->toArray(),
            ['is_default' => false]
        );

        // Set the specified tenant as default
        $this->tenants()->updateExistingPivot($tenantId, ['is_default' => true]);
    }

    /**
     * Get the role for this model in a specific tenant.
     */
    public function getTenantRole(int|string|Tenant $tenant): ?string
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $tenantsTable = config('laratenauth.database.tenants_table', 'tenants');

        $pivotRecord = $this->tenants()
            ->where("{$tenantsTable}.id", $tenantId)
            ->first();

        return $pivotRecord?->pivot?->role;
    }

    /**
     * Set the role for this model in a specific tenant.
     */
    public function setTenantRole(int|string|Tenant $tenant, ?string $role): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        $this->tenants()->updateExistingPivot($tenantId, ['role' => $role]);
    }

    /**
     * Get the permissions for this model in a specific tenant.
     */
    public function getTenantPermissions(int|string|Tenant $tenant): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $tenantsTable = config('laratenauth.database.tenants_table', 'tenants');

        $pivotRecord = $this->tenants()
            ->where("{$tenantsTable}.id", $tenantId)
            ->first();

        $permissions = $pivotRecord?->pivot?->permissions;

        if (is_string($permissions)) {
            return json_decode($permissions, true) ?? [];
        }

        return $permissions ?? [];
    }

    /**
     * Set permissions for this model in a specific tenant.
     */
    public function setTenantPermissions(int|string|Tenant $tenant, array $permissions): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        $this->tenants()->updateExistingPivot($tenantId, [
            'permissions' => json_encode($permissions),
        ]);
    }

    /**
     * Add a permission for this model in a specific tenant.
     */
    public function addTenantPermission(int|string|Tenant $tenant, string $permission): void
    {
        $permissions = $this->getTenantPermissions($tenant);

        if (! in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->setTenantPermissions($tenant, $permissions);
        }
    }

    /**
     * Remove a permission for this model in a specific tenant.
     */
    public function removeTenantPermission(int|string|Tenant $tenant, string $permission): void
    {
        $permissions = $this->getTenantPermissions($tenant);

        $permissions = array_filter($permissions, fn ($p) => $p !== $permission);
        $this->setTenantPermissions($tenant, array_values($permissions));
    }

    /**
     * Check if this model has a specific permission in a tenant.
     */
    public function hasTenantPermission(int|string|Tenant $tenant, string $permission): bool
    {
        $permissions = $this->getTenantPermissions($tenant);

        // Wildcard permission
        if (in_array('*', $permissions)) {
            return true;
        }

        return in_array($permission, $permissions);
    }

    /**
     * Check if this model has a specific role in a tenant.
     */
    public function hasTenantRole(int|string|Tenant $tenant, string $role): bool
    {
        return $this->getTenantRole($tenant) === $role;
    }

    /**
     * Scope query to users belonging to a specific tenant.
     */
    public function scopeInTenant($query, int|string|Tenant $tenant): mixed
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $tenantsTable = config('laratenauth.database.tenants_table', 'tenants');

        return $query->whereHas('tenants', function ($q) use ($tenantId, $tenantsTable) {
            $q->where("{$tenantsTable}.id", $tenantId);
        });
    }

    /**
     * Scope query to users with a specific role in a tenant.
     */
    public function scopeWithTenantRole($query, int|string|Tenant $tenant, string $role): mixed
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $tenantsTable = config('laratenauth.database.tenants_table', 'tenants');
        $pivotTable = config('laratenauth.database.tenant_user_table', 'tenant_user');

        return $query->whereHas('tenants', function ($q) use ($tenantId, $role, $tenantsTable, $pivotTable) {
            $q->where("{$tenantsTable}.id", $tenantId)
                ->where("{$pivotTable}.role", $role);
        });
    }
}
