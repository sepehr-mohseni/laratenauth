<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantNotResolvedException;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Symfony\Component\HttpFoundation\Response;

class PreventAccessFromTenant
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected TenantContextManager $tenantContext
    ) {
    }

    /**
     * Handle an incoming request.
     * This middleware ensures the route can only be accessed without a tenant context.
     * Useful for "central" routes that should not be accessed with a tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenantContext->hasTenant()) {
            throw TenantNotResolvedException::cannotAccessWithTenant();
        }

        return $next($request);
    }
}
