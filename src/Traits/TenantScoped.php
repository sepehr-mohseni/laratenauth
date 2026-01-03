<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;

/**
 * Trait for Eloquent models that should be scoped to the current tenant.
 *
 * @mixin Model
 */
trait TenantScoped
{
    /**
     * Whether to apply automatic tenant scoping.
     */
    protected static bool $enableTenantScope = true;

    /**
     * Boot the TenantScoped trait.
     */
    public static function bootTenantScoped(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (! static::$enableTenantScope) {
                return;
            }

            $tenant = TenantAuth::tenant();

            if ($tenant) {
                $tenantColumn = static::getTenantColumn();
                $builder->where($tenantColumn, $tenant->getKey());
            }
        });

        static::creating(function (Model $model) {
            if (! static::$enableTenantScope) {
                return;
            }

            $tenantColumn = static::getTenantColumn();

            // Only set tenant_id if not already set
            if (! $model->{$tenantColumn}) {
                $tenant = TenantAuth::tenant();

                if ($tenant) {
                    $model->{$tenantColumn} = $tenant->getKey();
                }
            }
        });
    }

    /**
     * Get the tenant column name.
     *
     * Models using this trait can define a TENANT_COLUMN constant to customize the column name.
     * If not defined, defaults to 'tenant_id'.
     *
     * @return string
     */
    public static function getTenantColumn(): string
    {
        if (defined('static::TENANT_COLUMN')) {
            // @phpstan-ignore-next-line Access to undefined constant
            return constant('static::TENANT_COLUMN');
        }

        return 'tenant_id';
    }

    /**
     * Disable tenant scoping.
     */
    public static function disableTenantScope(): void
    {
        static::$enableTenantScope = false;
    }

    /**
     * Enable tenant scoping.
     */
    public static function enableTenantScope(): void
    {
        static::$enableTenantScope = true;
    }

    /**
     * Check if tenant scoping is enabled.
     */
    public static function isTenantScopeEnabled(): bool
    {
        return static::$enableTenantScope;
    }

    /**
     * Execute a callback without tenant scoping.
     */
    public static function withoutTenantScope(callable $callback): mixed
    {
        $previousState = static::$enableTenantScope;
        static::disableTenantScope();

        try {
            return $callback();
        } finally {
            static::$enableTenantScope = $previousState;
        }
    }

    /**
     * Execute a callback with a specific tenant scope.
     */
    public static function withTenant(int|string|Tenant $tenant, callable $callback): mixed
    {
        return TenantAuth::executeInTenant($tenant, $callback);
    }

    /**
     * Scope query to a specific tenant (bypasses global scope).
     */
    public function scopeForTenant($query, int|string|Tenant $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $tenantColumn = static::getTenantColumn();

        return $query->withoutGlobalScope('tenant')->where($tenantColumn, $tenantId);
    }

    /**
     * Scope query to all tenants (bypasses global scope).
     */
    public function scopeAllTenants($query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Get the tenant for this model.
     */
    public function tenant(): ?\Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        $tenantModel = config('laratenauth.tenant_model', Tenant::class);
        $tenantColumn = static::getTenantColumn();

        return $this->belongsTo($tenantModel, $tenantColumn);
    }

    /**
     * Check if this model belongs to a specific tenant.
     */
    public function belongsToTenant(int|string|Tenant $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $tenantColumn = static::getTenantColumn();

        return $this->{$tenantColumn} == $tenantId;
    }

    /**
     * Set the tenant for this model.
     */
    public function setTenant(int|string|Tenant $tenant): self
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $tenantColumn = static::getTenantColumn();

        $this->{$tenantColumn} = $tenantId;

        return $this;
    }

    /**
     * Get the tenant ID for this model.
     */
    public function getTenantId(): int|string|null
    {
        $tenantColumn = static::getTenantColumn();

        return $this->{$tenantColumn};
    }
}
