# Usage Guide

This guide provides detailed examples and use cases for the Laravel Multi-Tenant Auth package.

## Table of Contents

1. [Installation & Setup](#installation--setup)
2. [Tenant Resolution](#tenant-resolution)
3. [Authentication](#authentication)
4. [User Management](#user-management)
5. [API Tokens](#api-tokens)
6. [Middleware](#middleware)
7. [Advanced Features](#advanced-features)

## Installation & Setup

### 1. Install Package

```bash
composer require sepehrmohseni/laravel-laratenauth
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag="laratenauth-config"
php artisan vendor:publish --tag="laratenauth-migrations"
php artisan migrate
```

### 3. Update Models

**User Model:**
```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Sepehr_Mohseni\LaraTenAuth\Traits\HasTenants;
use Sepehr_Mohseni\LaraTenAuth\Traits\HasTenantTokens;

class User extends Authenticatable
{
    use HasTenants, HasTenantTokens;
    
    // Your existing code...
}
```

**Tenant Model (Optional - use package model or extend it):**
```php
<?php

namespace App\Models;

use Sepehr_Mohseni\LaraTenAuth\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    // Add custom methods or relationships
}
```

### 4. Configure Authentication Guards

In `config/auth.php`, add tenant guards:

```php
'guards' => [
    'web' => [
        'driver' => 'tenant-session',
        'provider' => 'users',
    ],
    
    'api' => [
        'driver' => 'tenant-token',
        'provider' => 'users',
    ],
],
```

## Tenant Resolution

### Subdomain-Based Resolution

```php
// config/laratenauth.php
'resolution' => [
    'strategies' => ['subdomain'],
    'central_domains' => ['myapp.com'],
],

// URLs:
// https://acme.myapp.com     -> Tenant: acme
// https://company.myapp.com  -> Tenant: company
```

### Domain-Based Resolution

```php
'resolution' => [
    'strategies' => ['domain'],
],

// URLs:
// https://acme.com           -> Tenant: acme.com
// https://company.io         -> Tenant: company.io
```

### Header-Based Resolution

```php
'resolution' => [
    'strategies' => ['header'],
    'header_name' => 'X-Tenant-ID',
],

// Request with header:
// X-Tenant-ID: 123
```

### Path-Based Resolution

```php
'resolution' => [
    'strategies' => ['path'],
    'path_parameter' => 'tenant',
],

// Routes:
Route::prefix('{tenant}')->middleware('tenant.identify')->group(function () {
    Route::get('/dashboard', ...);
});

// URL: https://myapp.com/acme/dashboard
```

### Multiple Strategies

```php
'resolution' => [
    'strategies' => ['subdomain', 'header', 'domain', 'path'],
],

// Will try strategies in order until tenant is found
```

### Custom Resolution Strategy

```php
use Sepehr_Mohseni\LaraTenAuth\Contracts\TenantResolverInterface;

app(TenantResolverInterface::class)->registerStrategy('custom', function ($request, $resolver) {
    // Get tenant from custom source (e.g., API key)
    $apiKey = $request->header('X-API-Key');
    
    return Tenant::where('api_key', $apiKey)->first();
});

// Enable in config
'resolution' => [
    'strategies' => ['custom'],
],
```

## Authentication

### Session-Based Authentication

```php
// Login Controller
use Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth;

public function login(Request $request)
{
    $credentials = $request->only('email', 'password');
    
    if (Auth::guard('web')->attempt($credentials)) {
        $request->session()->regenerate();
        
        return redirect()->intended('dashboard');
    }
    
    return back()->withErrors([
        'email' => 'Invalid credentials or no tenant access.',
    ]);
}

// The guard automatically verifies tenant access
```

### API Token Authentication

```php
// Generate token for user
$user = User::find(1);
$tenant = Tenant::find(1);

$token = $user->createTenantToken(
    $tenant,
    'mobile-app',
    ['read', 'write'],
    now()->addYear()
);

// Return to user (only available when creating)
return response()->json([
    'token' => $token->plainTextToken,
]);

// Make authenticated API request
// Authorization: Bearer {token}
```

### Multiple Tenants Per User

```php
// User can authenticate to different tenants
$user->attachToTenant($tenant1, 'admin');
$user->attachToTenant($tenant2, 'member');

// Switch between tenants
TenantAuth::switchTenant($tenant2);

// Or via middleware
Route::post('/switch-tenant', function (Request $request) {
    // Send switch_tenant_id parameter
})->middleware('tenant.switch');
```

## User Management

### Adding Users to Tenants

```php
// Invite user to tenant
$user->attachToTenant($tenant, 'member', ['read'], [
    'invited_by' => auth()->id(),
    'department' => 'Engineering',
]);

// Update role
$user->updateRoleInTenant($tenant, 'admin');

// Deactivate user
$user->deactivateInTenant($tenant);

// Reactivate user
$user->activateInTenant($tenant);

// Remove user from tenant
$user->detachFromTenant($tenant);
```

### Checking Access

```php
// Check if user belongs to tenant
if ($user->belongsToTenant($tenant)) {
    // User is member
}

// Check if user has active access
if ($user->hasAccessToTenant($tenant)) {
    // User can access
}

// Check user's role
if ($user->hasRoleInTenant($tenant, ['admin', 'owner'])) {
    // User is admin or owner
}

// Get user's role
$role = $user->getRoleInTenant($tenant); // 'admin', 'member', etc.

// Get user's permissions
$permissions = $user->getPermissionsInTenant($tenant);
```

### Listing User's Tenants

```php
// Get all tenants
$tenants = $user->tenants;

// Get only active tenants
$activeTenants = $user->activeTenants;

// Query with conditions
$adminTenants = $user->tenants()
    ->wherePivot('role', 'admin')
    ->get();
```

## API Tokens

### Creating Tokens

```php
// Full-access token
$token = $user->createTenantToken($tenant, 'api-access', ['*']);

// Limited abilities
$token = $user->createTenantToken($tenant, 'read-only', ['read']);

// With expiration
$token = $user->createTenantToken(
    $tenant,
    'temporary',
    ['read'],
    now()->addDays(7)
);

// Store the plain text token securely
$plainTextToken = $token->plainTextToken;
```

### Validating Tokens

```php
// In middleware
Route::middleware(['tenant.token:read,write'])->group(function () {
    // Token must have read AND write abilities
});

// Programmatically
$token = TenantToken::findToken($providedToken);

if ($token && $token->isValid() && $token->can('write')) {
    // Token is valid and has write ability
}
```

### Managing Tokens

```php
// Get all tokens for tenant
$tokens = $user->tokensForTenant($tenant)->get();

// Revoke specific token
$user->revokeToken($token);

// Revoke all tokens for tenant
$user->revokeTenantTokens($tenant);

// Check current token
$currentToken = $user->currentAccessToken();
if ($currentToken->can('delete')) {
    // Can delete
}
```

## Middleware

### Available Middleware

1. **tenant.identify** - Identifies tenant from request
2. **tenant.auth** - Authenticates user in tenant context
3. **tenant.access** - Ensures user has tenant access
4. **tenant.token** - Validates API token
5. **tenant.switch** - Allows tenant switching

### Usage Examples

```php
// Public routes with tenant context
Route::middleware('tenant.identify')->group(function () {
    Route::get('/pricing', [PricingController::class, 'index']);
});

// Protected routes
Route::middleware(['tenant.identify', 'tenant.auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// Strict tenant access verification
Route::middleware(['tenant.identify', 'tenant.auth', 'tenant.access'])->group(function () {
    Route::resource('projects', ProjectController::class);
});

// API routes with token abilities
Route::middleware(['tenant.identify', 'tenant.token:read,write'])->group(function () {
    Route::apiResource('posts', PostController::class);
});

// Allow tenant switching
Route::middleware(['tenant.auth', 'tenant.switch'])->group(function () {
    Route::get('/workspace', [WorkspaceController::class, 'index']);
});
```

## Advanced Features

### Tenant Scoping Models

```php
use Sepehr_Mohseni\LaraTenAuth\Traits\TenantScoped;

class Project extends Model
{
    use TenantScoped;
    
    // All queries automatically scoped to current tenant
}

// Usage
$projects = Project::all(); // Only current tenant's projects

// Query without scope
$allProjects = Project::withoutTenantScope()->get();

// Query specific tenant
$projects = Project::forTenant($otherTenant)->get();
```

### Executing in Tenant Context

```php
use Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth;

// Execute closure in specific tenant
$result = TenantAuth::executeInTenant($tenant, function () {
    // This code runs in $tenant context
    return Project::all(); // Returns $tenant's projects
});

// Helper function
$result = in_tenant($tenant, function () {
    // Code here
});
```

### User Impersonation

```php
// Enable in config
'security' => [
    'allow_impersonation' => true,
],

// Impersonate user
TenantAuth::impersonate($otherUser, $tenant);

// Stop impersonating
TenantAuth::stopImpersonating();

// Check if impersonating
if (TenantAuth::isImpersonating()) {
    $originalUser = TenantAuth::getOriginalUser();
}
```

### Event Listeners

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \Sepehr_Mohseni\LaraTenAuth\Events\TenantSwitched::class => [
        \App\Listeners\LogTenantSwitch::class,
    ],
    \Sepehr_Mohseni\LaraTenAuth\Events\TenantAuthenticated::class => [
        \App\Listeners\SetupTenantContext::class,
    ],
];

// app/Listeners/LogTenantSwitch.php
class LogTenantSwitch
{
    public function handle(TenantSwitched $event)
    {
        Log::info('Tenant switched', [
            'user_id' => auth()->id(),
            'from_tenant' => $event->previousTenant?->id,
            'to_tenant' => $event->newTenant->id,
        ]);
    }
}
```

### Helper Functions

```php
// Get current tenant
$tenant = tenant();

// Get tenant ID
$tenantId = tenant_id();

// Set tenant
set_tenant($tenant);

// Execute in tenant
$result = in_tenant($tenant, function () {
    // Code here
});

// Get authenticated user
$user = tenant_user();
```

### Custom Guard Configuration

```php
// Per-tenant authentication drivers
$tenant = Tenant::find(1);
$tenant->auth_driver = 'sanctum';
$tenant->auth_config = [
    'token_expiration' => 43200, // Custom expiration
    'abilities' => ['custom-ability'],
];
$tenant->save();

// The package will use these settings for this tenant
```

## Best Practices

1. **Always identify tenant early** - Use `tenant.identify` middleware on all routes
2. **Verify tenant access** - Use `tenant.access` middleware for protected resources
3. **Scope models** - Use `TenantScoped` trait on all tenant-specific models
4. **Cache tenant data** - Enable tenant resolution caching for performance
5. **Log events** - Listen to tenant events for auditing
6. **Validate tokens** - Always check token abilities and expiration
7. **Use helpers** - Leverage helper functions for cleaner code
8. **Test thoroughly** - Write tests for all tenant-specific logic

## Troubleshooting

### Tenant Not Resolving

- Check resolution strategy configuration
- Verify tenant exists and is active
- Check central domain configuration for subdomain strategy
- Enable debug logging to see resolution attempts

### Authentication Failing

- Verify user has access to tenant (`hasAccessToTenant`)
- Check if user is active in tenant
- Ensure correct guard is being used
- Verify tenant context is set before authentication

### Token Issues

- Check token hasn't expired
- Verify token belongs to current tenant
- Ensure token has required abilities
- Validate token hash matches

### Session Issues

- Enable session isolation in config
- Check session driver supports tenant isolation
- Verify session regeneration on tenant switch
- Clear browser cookies if switching between tenants
