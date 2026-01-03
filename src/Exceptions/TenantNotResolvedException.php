<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Exceptions;

use Exception;

class TenantNotResolvedException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @return self
     */
    public static function make(): self
    {
        $message = config('laratenauth.messages.tenant_not_found', 'Tenant could not be resolved from the current request.');

        return new self($message);
    }

    /**
     * Create exception for when route cannot be accessed with a tenant context.
     *
     * @return self
     */
    public static function cannotAccessWithTenant(): self
    {
        return new self('This route cannot be accessed with a tenant context.');
    }
}
