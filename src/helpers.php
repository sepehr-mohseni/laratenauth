<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantAuthManager;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;

if (! function_exists('tenant')) {
    /**
     * Get the current tenant.
     */
    function tenant(): ?Model
    {
        return app(TenantAuthManager::class)->tenant();
    }
}

if (! function_exists('tenant_id')) {
    /**
     * Get the current tenant's ID.
     */
    function tenant_id(): int|string|null
    {
        $tenant = tenant();

        return $tenant?->getKey();
    }
}

if (! function_exists('has_tenant')) {
    /**
     * Check if a tenant context is active.
     */
    function has_tenant(): bool
    {
        return app(TenantContextManager::class)->hasTenant();
    }
}

if (! function_exists('tenant_user')) {
    /**
     * Get the currently authenticated user (tenant-scoped).
     */
    function tenant_user(?string $guard = null): ?Authenticatable
    {
        return app(TenantAuthManager::class)->user($guard);
    }
}

if (! function_exists('in_tenant')) {
    /**
     * Execute a callback in a specific tenant context.
     */
    function in_tenant(int|string|Model $tenant, callable $callback): mixed
    {
        return app(TenantAuthManager::class)->executeInTenant($tenant, $callback);
    }
}

if (! function_exists('tenant_setting')) {
    /**
     * Get a setting from the current tenant.
     */
    function tenant_setting(string $key, mixed $default = null): mixed
    {
        $tenant = tenant();

        if ($tenant && method_exists($tenant, 'getSetting')) {
            return $tenant->getSetting($key, $default);
        }

        return $default;
    }
}

if (! function_exists('tenant_meta')) {
    /**
     * Get metadata from the current tenant.
     */
    function tenant_meta(string $key, mixed $default = null): mixed
    {
        $tenant = tenant();

        if ($tenant && method_exists($tenant, 'getMeta')) {
            return $tenant->getMeta($key, $default);
        }

        return $default;
    }
}
