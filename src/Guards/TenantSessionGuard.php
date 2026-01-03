<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Guards;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Timebox;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantAuthenticated;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantAccessDeniedException;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class TenantSessionGuard extends SessionGuard implements StatefulGuard
{
    use GuardHelpers;

    /**
     * The tenant context manager.
     */
    protected ?TenantContextManager $tenantContext = null;

    /**
     * Whether cross-tenant auth is allowed.
     */
    protected bool $allowCrossTenant = false;

    /**
     * Create a new authentication guard.
     */
    public function __construct(
        string $name,
        UserProvider $provider,
        Session $session,
        SymfonyRequest|Request|null $request = null,
        ?Timebox $timebox = null
    ) {
        parent::__construct($name, $provider, $session, $request, $timebox);
    }

    /**
     * Set the tenant context manager.
     */
    public function setTenantContext(TenantContextManager $tenantContext): self
    {
        $this->tenantContext = $tenantContext;

        return $this;
    }

    /**
     * Set whether cross-tenant auth is allowed.
     */
    public function setCrossTenantAuth(bool $allowed): self
    {
        $this->allowCrossTenant = $allowed;

        return $this;
    }

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?Authenticatable
    {
        if ($this->loggedOut) {
            return null;
        }

        if ($this->user !== null) {
            return $this->user;
        }

        $user = parent::user();

        if ($user && ! $this->validateUserTenant($user)) {
            $this->user = null;

            return null;
        }

        return $user;
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * @param  array<string, mixed>  $credentials
     * @param  bool  $remember
     * @return bool
     */
    public function attempt(array $credentials = [], $remember = false)
    {
        $this->fireAttemptEvent($credentials, (bool) $remember);

        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->hasValidCredentials($user, $credentials) && $this->validateUserTenant($user)) {
            $this->login($user, $remember);

            return true;
        }

        $this->fireFailedEvent($user, $credentials);

        return false;
    }

    /**
     * Log a user into the application.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  bool  $remember
     * @return void
     */
    public function login(Authenticatable $user, $remember = false)
    {
        if (! $this->validateUserTenant($user)) {
            $tenant = $this->tenantContext?->getTenant();
            throw TenantAccessDeniedException::forUser($user, $tenant);
        }

        parent::login($user, $remember);

        $tenant = $this->tenantContext?->getTenant();
        if ($tenant) {
            $this->fireEvent(new TenantAuthenticated($tenant, $user));
        }
    }

    /**
     * Validate the user belongs to the current tenant.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return bool
     */
    protected function validateUserTenant(Authenticatable $user): bool
    {
        if ($this->tenantContext === null) {
            return true;
        }

        $tenant = $this->tenantContext->getTenant();

        if ($tenant === null) {
            return $this->allowCrossTenant;
        }

        if (! method_exists($user, 'belongsToTenant')) {
            return true;
        }

        /** @var \Sepehr_Mohseni\LaraTenAuth\Traits\HasTenants $user */
        return $user->belongsToTenant($tenant);
    }

    /**
     * Fire an event.
     */
    protected function fireEvent(object $event): void
    {
        if (isset($this->events)) {
            $this->events->dispatch($event);
        }
    }

    /**
     * Set the event dispatcher instance.
     */
    public function setDispatcher(Dispatcher $events): void
    {
        $this->events = $events;
    }
}
