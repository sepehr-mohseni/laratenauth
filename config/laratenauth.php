<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | The model that represents a tenant in your application.
    |
    */
    'tenant_model' => env('LARATENAUTH_TENANT_MODEL', 'App\Models\Tenant'),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The model that represents a user in your application.
    |
    */
    'user_model' => env('LARATENAUTH_USER_MODEL', 'App\Models\User'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution
    |--------------------------------------------------------------------------
    |
    | Configure how tenants are identified from incoming requests.
    | Available strategies: subdomain, domain, path, header, manual
    |
    */
    'resolution' => [
        // Strategies to use for tenant identification (in priority order)
        'strategies' => ['subdomain', 'header', 'domain', 'path'],

        // Header name for header-based identification
        'header_name' => 'X-Tenant-ID',

        // Path parameter name for path-based identification
        'path_parameter' => 'tenant',

        // Cache tenant resolution for better performance
        'cache_enabled' => true,
        'cache_ttl' => 3600, // seconds

        // Central domain (for subdomain strategy)
        'central_domains' => [
            env('APP_DOMAIN', 'localhost'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Default guards configuration for tenant authentication.
    | Each tenant can override these settings.
    |
    */
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'tenant-users',
        ],
        'api' => [
            'driver' => 'sanctum',
            'provider' => 'tenant-users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-specific session behavior.
    |
    */
    'session' => [
        // Isolate sessions per tenant
        'isolated' => true,

        // Prefix for tenant session keys
        'prefix' => 'tenant_',

        // Session lifetime in minutes (null = use Laravel default)
        'lifetime' => null,

        // Regenerate session on tenant switch
        'regenerate_on_switch' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for API tokens (Sanctum/JWT).
    |
    */
    'tokens' => [
        // Token expiration in minutes (null = no expiration)
        'expiration' => 525600, // 1 year

        // Enable token refresh functionality
        'refresh_enabled' => true,

        // Refresh token expiration in minutes
        'refresh_expiration' => 1051200, // 2 years

        // Token abilities/scopes
        'abilities' => [
            'read',
            'write',
            'delete',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure security features for tenant authentication.
    |
    */
    'security' => [
        // Rate limiting for login attempts
        'rate_limit' => [
            'enabled' => true,
            'max_attempts' => 5,
            'decay_minutes' => 1,
            'per_tenant' => true,
        ],

        // Allow user impersonation (admin feature)
        'allow_impersonation' => env('LARATENAUTH_ALLOW_IMPERSONATION', false),

        // Require tenant access verification
        'enforce_tenant_access' => true,

        // Log authentication events
        'log_auth_events' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database settings for multi-tenant architecture.
    |
    */
    'database' => [
        // Database architecture: 'single' or 'multi'
        'architecture' => env('LARATENAUTH_DB_ARCHITECTURE', 'single'),

        // Table names
        'tenants_table' => 'tenants',
        'tenant_user_table' => 'tenant_user',
        'tokens_table' => 'tenant_tokens',

        // Use UUIDs instead of auto-incrementing IDs
        'use_uuid' => env('LARATENAUTH_USE_UUID', false),

        // Connection name for tenant database (multi-database architecture)
        'tenant_connection' => env('LARATENAUTH_DB_CONNECTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Model
    |--------------------------------------------------------------------------
    |
    | The model used for tenant API tokens.
    |
    */
    'token_model' => Sepehr_Mohseni\LaraTenAuth\Models\TenantToken::class,

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Configure which events should be dispatched.
    |
    */
    'events' => [
        'tenant_identified' => true,
        'tenant_authenticated' => true,
        'tenant_switched' => true,
        'tenant_access_denied' => true,
        'token_created' => true,
        'token_revoked' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Configure middleware behavior.
    |
    */
    'middleware' => [
        // Automatically register middleware
        'auto_register' => true,

        // Middleware aliases
        'aliases' => [
            'tenant.identify' => \Sepehr_Mohseni\LaraTenAuth\Middleware\IdentifyTenant::class,
            'tenant.context' => \Sepehr_Mohseni\LaraTenAuth\Middleware\EnsureTenantContext::class,
            'tenant.access' => \Sepehr_Mohseni\LaraTenAuth\Middleware\EnsureTenantAccess::class,
            'tenant.role' => \Sepehr_Mohseni\LaraTenAuth\Middleware\EnsureTenantRole::class,
            'tenant.prevent' => \Sepehr_Mohseni\LaraTenAuth\Middleware\PreventAccessFromTenant::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    |
    | Customizable messages for exceptions and responses.
    | Override these to provide localized or custom error messages.
    |
    */
    'messages' => [
        'tenant_not_found' => 'The requested tenant does not exist.',
        'tenant_inactive' => 'This tenant account is currently inactive.',
        'access_denied' => 'You do not have access to this tenant.',
        'authentication_required' => 'Authentication is required.',
        'no_tenant_context' => 'No tenant context available.',
        'invalid_token' => 'The provided token is invalid.',
        'token_expired' => 'The token has expired.',
        'token_revoked' => 'The token has been revoked.',
        'token_wrong_tenant' => 'Token does not belong to the current tenant.',
        'user_no_tenant_access' => 'User :user_id does not have access to tenant :tenant_id.',
        'permission_denied' => 'Permission denied: :permission.',
        'role_required' => 'Required role: :roles.',
        'user_no_roles_support' => 'User model does not support tenant roles.',
        'impersonation_disabled' => 'User impersonation is disabled.',
        'impersonation_no_context' => 'No tenant context for impersonation.',
    ],
];
