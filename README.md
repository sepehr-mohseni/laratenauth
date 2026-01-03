# LaraTenAuth

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sepehr-mohseni/laratenauth.svg?style=flat-square)](https://packagist.org/packages/sepehr-mohseni/laratenauth)
[![Tests](https://img.shields.io/github/actions/workflow/status/sepehr-mohseni/laratenauth/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sepehr-mohseni/laratenauth/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/sepehr-mohseni/laratenauth.svg?style=flat-square)](https://packagist.org/packages/sepehr-mohseni/laratenauth)
[![License](https://img.shields.io/packagist/l/sepehr-mohseni/laratenauth.svg?style=flat-square)](https://packagist.org/packages/sepehr-mohseni/laratenauth)

A comprehensive multi-tenant authentication package for Laravel applications. LaraTenAuth provides complete tenant isolation, multiple authentication drivers, role-based access control, and seamless tenant switching capabilities.

## Features

- 🏢 **Multiple Tenant Resolution Strategies** - Subdomain, domain, path, header, or custom strategies
- 🔐 **Tenant-Scoped Authentication** - Session and token-based guards with tenant isolation
- 👥 **Multi-Tenant User Support** - Users can belong to multiple tenants with different roles
- 🎭 **Role & Permission Management** - Per-tenant roles and permissions for fine-grained access control
- 🔄 **Tenant Switching** - Switch between tenants while maintaining session state
- 🎫 **Tenant-Scoped API Tokens** - Create tokens scoped to specific tenants
- 🛡️ **Security Features** - Impersonation support, cross-tenant access control
- ⚡ **Performance Optimized** - Built-in caching for tenant resolution
- 🧪 **Fully Tested** - Comprehensive test suite with 90%+ coverage

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x

## Installation

You can install the package via composer:

```bash
composer require sepehr-mohseni/laratenauth
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=laratenauth-config
```

Publish the migrations:

```bash
php artisan vendor:publish --tag=laratenauth-migrations
```

Run the migrations:

```bash
php artisan migrate
```

## Configuration

The configuration file is located at `config/laratenauth.php`. Key configuration options:

```php
return [
    // The tenant model class
    'tenant_model' => \Sepehr_Mohseni\LaraTenAuth\Models\Tenant::class,

    // The user model class
    'user_model' => \App\Models\User::class,

    // Tenant resolution strategies (order matters)
    'resolution' => [
        'strategies' => ['subdomain', 'domain', 'header'],
        'central_domains' => ['localhost', 'your-app.com'],
        'header_name' => 'X-Tenant-ID',
        'cache_enabled' => true,
        'cache_ttl' => 3600,
    ],

    // Security settings
    'security' => [
        'allow_impersonation' => false,
        'cross_tenant_auth' => false,
    ],
];
```

## Quick Start

### 1. Prepare Your User Model

Add the `HasTenants` and `HasTenantTokens` traits to your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Sepehr_Mohseni\LaraTenAuth\Traits\HasTenants;
use Sepehr_Mohseni\LaraTenAuth\Traits\HasTenantTokens;

class User extends Authenticatable
{
    use HasTenants, HasTenantTokens;

    // ...
}
```

### 2. Create a Tenant

```php
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;

$tenant = Tenant::create([
    'name' => 'Acme Inc.',
    'slug' => 'acme',
    'subdomain' => 'acme',
    'is_active' => true,
]);
```

### 3. Add Users to Tenants

```php
// Add user to tenant with a role
$user->joinTenant($tenant, 'admin', ['manage-users', 'manage-settings']);

// Or from the tenant side
$tenant->addUser($user, 'member');
```

### 4. Apply Middleware to Routes

```php
// routes/web.php
Route::middleware(['tenant.identify'])->group(function () {
    // These routes require a tenant context
    
    Route::middleware(['tenant.access'])->group(function () {
        // These routes require authenticated user with tenant access
        
        Route::middleware(['tenant.role:admin'])->group(function () {
            // These routes require admin role
        });
    });
});

// Central routes (no tenant required)
Route::middleware(['tenant.prevent'])->group(function () {
    Route::get('/pricing', [PricingController::class, 'index']);
});
```

## Usage

### Tenant Resolution

LaraTenAuth supports multiple strategies for resolving the current tenant:

#### Subdomain Resolution
```
https://acme.your-app.com → Tenant with subdomain 'acme'
```

#### Domain Resolution
```
https://acme-custom-domain.com → Tenant with domain 'acme-custom-domain.com'
```

#### Header Resolution
```
X-Tenant-ID: 123 → Tenant with ID 123
```

#### Custom Resolution Strategy
```php
use Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth;

// In a service provider
TenantAuth::registerStrategy('api-key', function (Request $request) {
    $apiKey = $request->header('X-API-Key');
    return Tenant::where('api_key', $apiKey)->first();
});
```

### Working with the Current Tenant

```php
use Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth;

// Get the current tenant
$tenant = TenantAuth::tenant();

// Check if a tenant context exists
if (TenantAuth::hasTenant()) {
    // ...
}

// Get tenant or throw exception
$tenant = TenantAuth::tenantOrFail();

// Using helper functions
$tenant = tenant();
$tenantId = tenant_id();
$hasTenant = has_tenant();
```

### Tenant Switching

```php
use Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth;

// Switch to another tenant (requires user to have access)
TenantAuth::switchTenant($anotherTenant);

// Execute code in a different tenant context
TenantAuth::executeInTenant($tenant, function ($tenant) {
    // Code runs with $tenant as the current tenant
    $users = User::all(); // Returns users for this tenant
});
```

### User-Tenant Relationships

```php
// Check if user belongs to tenant
$user->belongsToTenant($tenant);

// Check if user has access to tenant
$user->hasAccessToTenant($tenant);

// Get user's role in a tenant
$role = $user->getTenantRole($tenant);

// Check role
$user->hasTenantRole($tenant, 'admin');

// Manage permissions
$user->getTenantPermissions($tenant);
$user->hasTenantPermission($tenant, 'manage-users');
$user->addTenantPermission($tenant, 'manage-settings');
$user->removeTenantPermission($tenant, 'manage-settings');

// Get user's default tenant
$defaultTenant = $user->defaultTenant();

// Set default tenant
$user->setDefaultTenant($tenant);
```

### Tenant-Scoped API Tokens

```php
// Create a token scoped to a tenant
$result = $user->createTenantToken(
    name: 'mobile-app',
    abilities: ['read', 'write'],
    tenant: $tenant,
    expiresAt: now()->addDays(30)
);

$plainTextToken = $result['plainTextToken'];
$token = $result['token'];

// Get tokens for a specific tenant
$tokens = $user->getTenantTokensForTenant($tenant);

// Revoke tokens
$user->revokeTenantToken('mobile-app');
$user->revokeTenantTokensForTenant($tenant);
$user->revokeAllTenantTokens();

// Check token abilities
$token->can('read');
$token->cannot('delete');
```

### Tenant-Scoped Models

Add automatic tenant scoping to your Eloquent models:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sepehr_Mohseni\LaraTenAuth\Traits\TenantScoped;

class Project extends Model
{
    use TenantScoped;

    // Optionally customize the tenant column
    const TENANT_COLUMN = 'tenant_id';
}
```

```php
// All queries are automatically scoped to the current tenant
$projects = Project::all(); // Only returns projects for current tenant

// Query all tenants (bypass scoping)
$allProjects = Project::allTenants()->get();

// Query specific tenant
$projects = Project::forTenant($tenant)->get();

// Execute without tenant scope
Project::withoutTenantScope(function () {
    $allProjects = Project::all();
});
```

### Tenant Settings & Metadata

```php
// Settings
$tenant->setSetting('theme.color', 'blue');
$color = $tenant->getSetting('theme.color', 'default');

// Metadata
$tenant->setMeta('plan', 'premium');
$plan = $tenant->getMeta('plan');

// Using helper functions
$color = tenant_setting('theme.color');
$plan = tenant_meta('plan');
```

### Events

LaraTenAuth dispatches events for key actions:

```php
use Sepehr_Mohseni\LaraTenAuth\Events\TenantIdentified;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantAuthenticated;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantSwitched;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantAccessDenied;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantTokenCreated;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantTokenRevoked;

// Register listeners in EventServiceProvider
protected $listen = [
    TenantIdentified::class => [
        LogTenantAccess::class,
    ],
    TenantSwitched::class => [
        NotifyAdminOfSwitch::class,
    ],
];
```

### Middleware Reference

| Middleware | Alias | Description |
|------------|-------|-------------|
| `IdentifyTenant` | `tenant.identify` | Resolves and sets the current tenant |
| `EnsureTenantContext` | `tenant.context` | Requires a tenant context |
| `EnsureTenantAccess` | `tenant.access` | Requires authenticated user with tenant access |
| `EnsureTenantRole` | `tenant.role` | Requires specific role(s) in tenant |
| `PreventAccessFromTenant` | `tenant.prevent` | Blocks access when tenant context exists |

## Database Schema

The package creates the following tables:

### tenants
```
- id (primary key)
- name
- slug (unique)
- domain (unique, nullable)
- subdomain (unique, nullable)
- is_active
- settings (json)
- metadata (json)
- timestamps
```

### tenant_user
```
- id (primary key)
- tenant_id (foreign key)
- tenant_userable_type
- tenant_userable_id
- role
- permissions (json)
- is_default
- timestamps
```

### tenant_tokens
```
- id (primary key)
- tokenable_type
- tokenable_id
- tenant_id (foreign key, nullable)
- name
- token (hashed)
- abilities (json)
- revoked
- last_used_at
- expires_at
- timestamps
```

## Testing

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

## Security

If you discover any security-related issues, please email isepehrmohseni@gmail.com instead of using the issue tracker.

## Credits

- [Sepehr Mohseni](https://github.com/sepehr-mohseni)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Links

- [GitHub](https://github.com/sepehr-mohseni/laratenauth)
- [Packagist](https://packagist.org/packages/sepehr-mohseni/laratenauth)
- [LinkedIn](https://www.linkedin.com/in/sepehr-mohseni/)
