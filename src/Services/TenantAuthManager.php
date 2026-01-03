<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantAccessDeniedException;

class TenantAuthManager
{
    /**
     * The impersonated user.
     */
    protected ?Authenticatable $impersonatedUser = null;

    /**
     * The original user before impersonation.
     */
    protected ?Authenticatable $originalUser = null;

    /**
     * Create a new tenant auth manager instance.
     */
    public function __construct(
        protected Application $app,
        protected TenantContextManager $tenantContext,
        protected ConfigRepository $config
    ) {
    }

    /**
     * Get the current tenant.
     */
    public function tenant(): ?Model
    {
        return $this->tenantContext->getTenant();
    }

    /**
     * Get the current tenant or fail.
     */
    public function tenantOrFail(): Model
    {
        return $this->tenantContext->getTenantOrFail();
    }

    /**
     * Get the current authenticated user (tenant-scoped).
     */
    public function user(?string $guard = null): ?Authenticatable
    {
        if ($this->impersonatedUser !== null) {
            return $this->impersonatedUser;
        }

        return $this->auth()->guard($guard)->user();
    }

    /**
     * Switch to a different tenant.
     */
    public function switchTenant(int|string|Model $tenant): void
    {
        $user = $this->user();

        if (! $user) {
            throw TenantAccessDeniedException::make('User must be authenticated to switch tenants.');
        }

        // Resolve tenant model if needed
        $tenantModel = $tenant instanceof Model
            ? $tenant
            : $this->resolveTenantById($tenant);

        // Verify user has access
        if (! $this->hasAccess($tenantModel, $user)) {
            throw TenantAccessDeniedException::make('You do not have access to this tenant.');
        }

        $this->tenantContext->switchTenant($tenantModel);
    }

    /**
     * Check if user has access to a tenant.
     *
     * @param  int|string|\Illuminate\Database\Eloquent\Model  $tenant
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @return bool
     */
    public function hasAccess(int|string|Model $tenant, ?Authenticatable $user = null): bool
    {
        $user = $user ?? $this->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAccessToTenant')) {
            /** @var \Sepehr_Mohseni\LaraTenAuth\Traits\HasTenants $user */
            return $user->hasAccessToTenant($tenant);
        }

        return true;
    }

    /**
     * Execute a callback in a specific tenant context.
     */
    public function executeInTenant(int|string|Model $tenant, callable $callback): mixed
    {
        return $this->tenantContext->executeInTenant($tenant, $callback);
    }

    /**
     * Impersonate a user within the current tenant.
     */
    public function impersonate(Authenticatable $user, ?Model $tenant = null): void
    {
        if (! $this->config->get('laratenauth.security.allow_impersonation', false)) {
            throw new RuntimeException('User impersonation is disabled.');
        }

        $targetTenant = $tenant ?? $this->tenant();

        if (! $targetTenant) {
            throw TenantAccessDeniedException::make('No tenant context for impersonation.');
        }

        // Verify user has access to tenant
        if (! $this->hasAccess($targetTenant, $user)) {
            throw TenantAccessDeniedException::make('User does not have access to this tenant.');
        }

        $this->originalUser = $this->user();
        $this->impersonatedUser = $user;

        if ($tenant) {
            $this->tenantContext->setTenant($tenant);
        }
    }

    /**
     * Stop impersonating and return to original user.
     */
    public function stopImpersonating(): void
    {
        $this->impersonatedUser = null;

        if ($this->originalUser !== null) {
            $this->originalUser = null;
        }
    }

    /**
     * Check if currently impersonating.
     */
    public function isImpersonating(): bool
    {
        return $this->impersonatedUser !== null;
    }

    /**
     * Get the original user (before impersonation).
     */
    public function getOriginalUser(): ?Authenticatable
    {
        return $this->originalUser;
    }

    /**
     * Resolve a tenant by ID.
     */
    protected function resolveTenantById(int|string $tenantId): Model
    {
        $tenantModel = $this->config->get('laratenauth.tenant_model');

        $tenant = $tenantModel::find($tenantId);

        if (! $tenant) {
            throw new RuntimeException("Tenant with ID {$tenantId} not found.");
        }

        return $tenant;
    }

    /**
     * Get the auth factory instance.
     */
    protected function auth(): AuthFactory
    {
        return $this->app->make(AuthFactory::class);
    }

    /**
     * Forward dynamic method calls to the tenant context manager.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->tenantContext->{$method}(...$parameters);
    }
}
