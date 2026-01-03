<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantNotResolvedException;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
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
     */
    public function handle(Request $request, Closure $next, bool $required = true): Response
    {
        $tenant = $this->tenantContext->identify($request);

        if ($tenant === null && $required) {
            throw TenantNotResolvedException::make();
        }

        return $next($request);
    }
}
