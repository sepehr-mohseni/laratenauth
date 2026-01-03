<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Facades;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantAuthManager;

/**
 * @method static Model|null tenant()
 * @method static Model tenantOrFail()
 * @method static Authenticatable|null user(?string $guard = null)
 * @method static void switchTenant(int|string|Model $tenant)
 * @method static bool hasAccess(int|string|Model $tenant, ?Authenticatable $user = null)
 * @method static mixed executeInTenant(int|string|Model $tenant, callable $callback)
 * @method static void impersonate(Authenticatable $user, ?Model $tenant = null)
 * @method static void stopImpersonating()
 * @method static bool isImpersonating()
 * @method static Authenticatable|null getOriginalUser()
 * @method static bool hasTenant()
 * @method static void setTenant(?Model $tenant)
 * @method static Model|null getTenant()
 * @method static Model getTenantOrFail()
 * @method static void clearTenant()
 * @method static Model|null getPreviousTenant()
 *
 * @see TenantAuthManager
 */
class TenantAuth extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'laratenauth';
    }
}
