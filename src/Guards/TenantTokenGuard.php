<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Guards;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantAuthenticated;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\InvalidTenantTokenException;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantAccessDeniedException;
use Sepehr_Mohseni\LaraTenAuth\Models\TenantToken;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;

class TenantTokenGuard implements Guard
{
    use GuardHelpers;

    /**
     * The name of the guard.
     */
    protected string $name;

    /**
     * The request instance.
     */
    protected Request $request;

    /**
     * The tenant context manager.
     */
    protected TenantContextManager $tenantContext;

    /**
     * The token model class.
     */
    protected string $tokenModel;

    /**
     * The name of the token input key.
     */
    protected string $inputKey = 'api_token';

    /**
     * The name of the token storage key.
     */
    protected string $storageKey = 'api_token';

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
        Request $request,
        string $tokenModel = TenantToken::class
    ) {
        $this->name = $name;
        $this->provider = $provider;
        $this->request = $request;
        $this->tokenModel = $tokenModel;
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
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getTokenForRequest();

        if (empty($token)) {
            return null;
        }

        $tokenRecord = $this->resolveToken($token);

        if ($tokenRecord === null) {
            return null;
        }

        $user = $tokenRecord->tokenable;

        if ($user && $this->validateUserTenant($user)) {
            $this->user = $user;

            return $user;
        }

        return null;
    }

    /**
     * Validate a user's credentials.
     */
    public function validate(array $credentials = []): bool
    {
        if (empty($credentials[$this->inputKey])) {
            return false;
        }

        $token = $this->resolveToken($credentials[$this->inputKey]);

        return $token !== null && $this->validateUserTenant($token->tokenable);
    }

    /**
     * Get the token from the request.
     */
    public function getTokenForRequest(): ?string
    {
        $token = $this->request->query($this->inputKey);

        if (empty($token)) {
            $token = $this->request->input($this->inputKey);
        }

        if (empty($token)) {
            $token = $this->request->bearerToken();
        }

        if (empty($token)) {
            $token = $this->request->getPassword();
        }

        return $token;
    }

    /**
     * Resolve a token record from the database.
     */
    protected function resolveToken(string $token): ?TenantToken
    {
        $tokenModel = $this->tokenModel;

        // Check if it's a hashed token or plain text
        if (str_contains($token, '|')) {
            [$id, $plainToken] = explode('|', $token, 2);

            /** @var TenantToken|null $tokenRecord */
            $tokenRecord = $tokenModel::where('id', $id)
                ->where('revoked', false)
                ->first();

            if (! $tokenRecord || ! hash_equals($tokenRecord->token, hash('sha256', $plainToken))) {
                return null;
            }
        } else {
            /** @var TenantToken|null $tokenRecord */
            $tokenRecord = $tokenModel::where('token', hash('sha256', $token))
                ->where('revoked', false)
                ->first();
        }

        if ($tokenRecord === null) {
            return null;
        }

        // Check expiration
        if ($tokenRecord->isExpired()) {
            return null;
        }

        // Check tenant restriction
        if (isset($this->tenantContext) && $tokenRecord->tenant_id) {
            $currentTenant = $this->tenantContext->getTenant();

            if ($currentTenant && $tokenRecord->tenant_id !== $currentTenant->getKey()) {
                if (! $this->allowCrossTenant) {
                    throw InvalidTenantTokenException::wrongTenant();
                }
            }
        }

        // Update last used timestamp
        $tokenRecord->markAsUsed();

        return $tokenRecord;
    }

    /**
     * Validate the user belongs to the current tenant.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return bool
     */
    protected function validateUserTenant(Authenticatable $user): bool
    {
        if (! isset($this->tenantContext)) {
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
     * Set the current request instance.
     */
    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }
}
