<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Exceptions;

use Exception;

class InvalidTenantTokenException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param  string|null  $message
     * @return self
     */
    public static function make(?string $message = null): self
    {
        $message = $message ?? config('laratenauth.messages.invalid_token', 'The provided token is invalid or has expired.');

        return new self($message);
    }

    /**
     * Create exception for token belonging to wrong tenant.
     *
     * @return self
     */
    public static function wrongTenant(): self
    {
        $message = config('laratenauth.messages.token_wrong_tenant', 'The token does not belong to the current tenant.');

        return new self($message);
    }

    /**
     * Create exception for expired token.
     *
     * @return self
     */
    public static function expired(): self
    {
        $message = config('laratenauth.messages.token_expired', 'The token has expired.');

        return new self($message);
    }

    /**
     * Create exception for revoked token.
     *
     * @return self
     */
    public static function revoked(): self
    {
        $message = config('laratenauth.messages.token_revoked', 'The token has been revoked.');

        return new self($message);
    }
}
