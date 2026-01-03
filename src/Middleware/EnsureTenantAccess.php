<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantAccessDenied;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantAccessDeniedException;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantAuthManager;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected TenantAuthManager $tenantAuth
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $this->tenantAuth->user();
        $tenant = $this->tenantAuth->tenant();

        if (! $user) {
            throw TenantAccessDeniedException::make(
                config('laratenauth.messages.authentication_required', 'Authentication required.')
            );
        }

        if (! $tenant) {
            throw TenantAccessDeniedException::make(
                config('laratenauth.messages.no_tenant_context', 'No tenant context.')
            );
        }

        // Check basic tenant access
        if (! $this->tenantAuth->hasAccess($tenant, $user)) {
            event(new TenantAccessDenied($user, $tenant));
            throw TenantAccessDeniedException::forUser($user, $tenant);
        }

        // Check specific permission if provided
        if ($permission !== null && method_exists($user, 'hasTenantPermission')) {
            /** @var \Sepehr_Mohseni\LaraTenAuth\Traits\HasTenants $user */
            if (! $user->hasTenantPermission($tenant, $permission)) {
                event(new TenantAccessDenied($user, $tenant));
                $message = config('laratenauth.messages.permission_denied', 'Permission denied: :permission.');
                throw TenantAccessDeniedException::make(str_replace(':permission', $permission, $message));
            }
        }

        return $next($request);
    }
}
