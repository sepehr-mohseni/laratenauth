<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Sepehr_Mohseni\LaraTenAuth\Contracts\TenantResolverInterface;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantIdentified;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantSwitched;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantNotResolvedException;

class TenantContextManager
{
    /**
     * The current tenant instance.
     */
    protected ?Model $currentTenant = null;

    /**
     * The previous tenant instance (for switching).
     */
    protected ?Model $previousTenant = null;

    /**
     * Create a new tenant context manager instance.
     */
    public function __construct(
        protected TenantResolverInterface $resolver,
        protected Dispatcher $events
    ) {
    }

    /**
     * Identify and set the current tenant from request.
     */
    public function identify(Request $request): ?Model
    {
        if ($this->currentTenant !== null) {
            return $this->currentTenant;
        }

        $tenant = $this->resolver->resolve($request);

        if ($tenant !== null) {
            $this->setTenant($tenant);
            $this->events->dispatch(new TenantIdentified($tenant));
        }

        return $tenant;
    }

    /**
     * Set the current tenant.
     */
    public function setTenant(?Model $tenant): void
    {
        $this->previousTenant = $this->currentTenant;
        $this->currentTenant = $tenant;
    }

    /**
     * Get the current tenant.
     */
    public function getTenant(): ?Model
    {
        return $this->currentTenant;
    }

    /**
     * Get the current tenant or throw an exception.
     */
    public function getTenantOrFail(): Model
    {
        if ($this->currentTenant === null) {
            throw TenantNotResolvedException::make();
        }

        return $this->currentTenant;
    }

    /**
     * Switch to a different tenant.
     */
    public function switchTenant(Model $tenant): void
    {
        $previous = $this->currentTenant;
        $this->setTenant($tenant);

        $this->events->dispatch(new TenantSwitched($previous, $tenant));
    }

    /**
     * Execute a callback in a specific tenant context.
     */
    public function executeInTenant(int|string|Model $tenant, callable $callback): mixed
    {
        $tenantModel = $tenant instanceof Model
            ? $tenant
            : $this->resolver->resolveById($tenant);

        if ($tenantModel === null) {
            throw TenantNotResolvedException::make();
        }

        $previous = $this->currentTenant;
        $this->setTenant($tenantModel);

        try {
            return $callback($tenantModel);
        } finally {
            $this->setTenant($previous);
        }
    }

    /**
     * Check if a tenant is currently set.
     */
    public function hasTenant(): bool
    {
        return $this->currentTenant !== null;
    }

    /**
     * Clear the current tenant.
     */
    public function clearTenant(): void
    {
        $this->previousTenant = $this->currentTenant;
        $this->currentTenant = null;
    }

    /**
     * Get the previous tenant.
     */
    public function getPreviousTenant(): ?Model
    {
        return $this->previousTenant;
    }
}
