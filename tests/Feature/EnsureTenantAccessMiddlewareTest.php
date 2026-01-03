<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantAccessDeniedException;
use Sepehr_Mohseni\LaraTenAuth\Middleware\EnsureTenantAccess;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantAuthManager;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class EnsureTenantAccessMiddlewareTest extends TestCase
{
    protected TenantAuthManager $authManager;
    protected TenantContextManager $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authManager = $this->app->make(TenantAuthManager::class);
        $this->context = $this->app->make(TenantContextManager::class);
    }

    public function test_passes_when_user_has_access(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->joinTenant($tenant);

        $this->actingAs($user);
        $this->context->setTenant($tenant);

        $middleware = new EnsureTenantAccess($this->authManager);

        $request = Request::create('http://example.com/api/users');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_throws_when_not_authenticated(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $this->context->setTenant($tenant);

        $middleware = new EnsureTenantAccess($this->authManager);

        $request = Request::create('http://example.com/api/users');

        $this->expectException(TenantAccessDeniedException::class);

        $middleware->handle($request, function ($req) {
            return new Response('OK');
        });
    }

    public function test_throws_when_no_tenant_context(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        $middleware = new EnsureTenantAccess($this->authManager);

        $request = Request::create('http://example.com/api/users');

        $this->expectException(TenantAccessDeniedException::class);

        $middleware->handle($request, function ($req) {
            return new Response('OK');
        });
    }

    public function test_throws_when_user_lacks_access(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
        // User not joined to tenant

        $this->actingAs($user);
        $this->context->setTenant($tenant);

        $middleware = new EnsureTenantAccess($this->authManager);

        $request = Request::create('http://example.com/api/users');

        $this->expectException(TenantAccessDeniedException::class);

        $middleware->handle($request, function ($req) {
            return new Response('OK');
        });
    }

    public function test_checks_permission_when_provided(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->joinTenant($tenant, null, ['read']);

        $this->actingAs($user);
        $this->context->setTenant($tenant);

        $middleware = new EnsureTenantAccess($this->authManager);

        $request = Request::create('http://example.com/api/users');

        // Should pass with 'read' permission
        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        }, 'read');

        $this->assertEquals(200, $response->getStatusCode());

        // Should fail with 'write' permission
        $this->expectException(TenantAccessDeniedException::class);

        $middleware->handle($request, function ($req) {
            return new Response('OK');
        }, 'write');
    }
}
