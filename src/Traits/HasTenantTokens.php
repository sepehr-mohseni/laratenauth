<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantTokenCreated;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantTokenRevoked;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Models\TenantToken;

/**
 * Trait for models that can have tenant-scoped API tokens.
 *
 * @mixin Model
 */
trait HasTenantTokens
{
    /**
     * Boot the HasTenantTokens trait.
     */
    public static function bootHasTenantTokens(): void
    {
        static::deleting(function (Model $model) {
            if (method_exists($model, 'tenantTokens')) {
                $model->tenantTokens()->delete();
            }
        });
    }

    /**
     * Get all tenant tokens for this model.
     *
     * @return MorphMany<TenantToken>
     */
    public function tenantTokens(): MorphMany
    {
        $tokenModel = config('laratenauth.token_model', TenantToken::class);

        return $this->morphMany($tokenModel, 'tokenable');
    }

    /**
     * Create a new tenant token.
     *
     * @return array{token: TenantToken, plainTextToken: string}
     */
    public function createTenantToken(
        string $name,
        array $abilities = ['*'],
        int|string|Tenant|null $tenant = null,
        ?Carbon $expiresAt = null
    ): array {
        $tenantId = null;

        if ($tenant !== null) {
            $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        }

        $tokenModel = config('laratenauth.token_model', TenantToken::class);

        $result = $tokenModel::createToken(
            $this,
            $name,
            $abilities,
            $tenantId,
            $expiresAt
        );

        // Dispatch event
        event(new TenantTokenCreated($result['token']));

        return $result;
    }

    /**
     * Get a specific token by name.
     */
    public function getTenantToken(string $name): ?TenantToken
    {
        return $this->tenantTokens()->where('name', $name)->first();
    }

    /**
     * Get all valid tokens for a specific tenant.
     */
    public function getTenantTokensForTenant(int|string|Tenant $tenant): \Illuminate\Database\Eloquent\Collection
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return $this->tenantTokens()
            ->where('tenant_id', $tenantId)
            ->valid()
            ->get();
    }

    /**
     * Revoke a token by name.
     */
    public function revokeTenantToken(string $name): bool
    {
        $token = $this->getTenantToken($name);

        if ($token) {
            $result = $token->revoke();

            if ($result) {
                event(new TenantTokenRevoked($token));
            }

            return $result;
        }

        return false;
    }

    /**
     * Revoke all tokens.
     */
    public function revokeAllTenantTokens(): int
    {
        return $this->tenantTokens()->update(['revoked' => true]);
    }

    /**
     * Revoke all tokens for a specific tenant.
     */
    public function revokeTenantTokensForTenant(int|string|Tenant $tenant): int
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return $this->tenantTokens()
            ->where('tenant_id', $tenantId)
            ->update(['revoked' => true]);
    }

    /**
     * Delete expired tokens.
     */
    public function deleteExpiredTenantTokens(): int
    {
        return $this->tenantTokens()->expired()->delete();
    }

    /**
     * Check if the model has any valid tokens.
     */
    public function hasValidTenantTokens(): bool
    {
        return $this->tenantTokens()->valid()->exists();
    }

    /**
     * Check if the model has any valid tokens for a specific tenant.
     */
    public function hasValidTenantTokensForTenant(int|string|Tenant $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return $this->tenantTokens()
            ->where('tenant_id', $tenantId)
            ->valid()
            ->exists();
    }

    /**
     * Get the current access token for this model.
     */
    public function currentTenantToken(): ?TenantToken
    {
        return $this->tenantTokens()
            ->valid()
            ->orderByDesc('last_used_at')
            ->first();
    }

    /**
     * Update token abilities.
     */
    public function updateTenantTokenAbilities(string $name, array $abilities): bool
    {
        $token = $this->getTenantToken($name);

        if ($token) {
            return $token->update(['abilities' => $abilities]);
        }

        return false;
    }
}
