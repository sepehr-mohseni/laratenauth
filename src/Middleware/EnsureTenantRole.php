<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantAccessDenied;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantAccessDeniedException;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantAuthManager;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantRole
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
    public function handle(Request $request, Closure $next, string ...$roles): Response
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

        // Check if user has any of the required roles
        if (! method_exists($user, 'getTenantRole')) {
            throw TenantAccessDeniedException::make(
                config('laratenauth.messages.user_no_roles_support', 'User model does not support tenant roles.')
            );
        }

        /** @var \Sepehr_Mohseni\LaraTenAuth\Traits\HasTenants $user */
        $userRole = $user->getTenantRole($tenant);

        if (! in_array($userRole, $roles, true)) {
            event(new TenantAccessDenied($user, $tenant));
            $message = config('laratenauth.messages.role_required', 'Required role: :roles.');
            throw TenantAccessDeniedException::make(str_replace(':roles', implode(' or ', $roles), $message));
        }

        return $next($request);
    }
}
