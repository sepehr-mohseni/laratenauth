<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Exceptions;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class TenantAccessDeniedException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param  string|null  $message
     * @return self
     */
    public static function make(?string $message = null): self
    {
        $message = $message ?? config('laratenauth.messages.access_denied', 'Access to this tenant is denied.');

        return new self($message);
    }

    /**
     * Create exception for a user who does not have access to the tenant.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  \Illuminate\Database\Eloquent\Model|null  $tenant
     * @return self
     */
    public static function forUser(Authenticatable $user, ?Model $tenant): self
    {
        /** @var \Illuminate\Database\Eloquent\Model $user */
        $userId = method_exists($user, 'getKey') ? $user->getKey() : 'unknown';
        $tenantId = $tenant?->getKey() ?? 'unknown';

        $message = config('laratenauth.messages.user_no_tenant_access', 'User :user_id does not have access to tenant :tenant_id.');
        $message = str_replace([':user_id', ':tenant_id'], [$userId, $tenantId], $message);

        return new self($message);
    }
}
