<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantAccessDeniedException;
use Sepehr_Mohseni\LaraTenAuth\Middleware\EnsureTenantRole;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantAuthManager;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class EnsureTenantRoleMiddlewareTest extends TestCase
{
    protected TenantAuthManager $authManager;
    protected TenantContextManager $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authManager = $this->app->make(TenantAuthManager::class);
        $this->context = $this->app->make(TenantContextManager::class);
    }

    public function test_passes_when_user_has_required_role(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->joinTenant($tenant, 'admin');

        $this->actingAs($user);
        $this->context->setTenant($tenant);

        $middleware = new EnsureTenantRole($this->authManager);

        $request = Request::create('http://example.com/api/users');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        }, 'admin');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_passes_with_any_of_multiple_roles(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->joinTenant($tenant, 'editor');

        $this->actingAs($user);
        $this->context->setTenant($tenant);

        $middleware = new EnsureTenantRole($this->authManager);

        $request = Request::create('http://example.com/api/users');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        }, 'admin', 'editor', 'viewer');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_throws_when_user_lacks_required_role(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->joinTenant($tenant, 'member');

        $this->actingAs($user);
        $this->context->setTenant($tenant);

        $middleware = new EnsureTenantRole($this->authManager);

        $request = Request::create('http://example.com/api/users');

        $this->expectException(TenantAccessDeniedException::class);

        $middleware->handle($request, function ($req) {
            return new Response('OK');
        }, 'admin');
    }

    public function test_throws_when_not_authenticated(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $this->context->setTenant($tenant);

        $middleware = new EnsureTenantRole($this->authManager);

        $request = Request::create('http://example.com/api/users');

        $this->expectException(TenantAccessDeniedException::class);

        $middleware->handle($request, function ($req) {
            return new Response('OK');
        }, 'admin');
    }

    public function test_throws_when_no_tenant_context(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        $middleware = new EnsureTenantRole($this->authManager);

        $request = Request::create('http://example.com/api/users');

        $this->expectException(TenantAccessDeniedException::class);

        $middleware->handle($request, function ($req) {
            return new Response('OK');
        }, 'admin');
    }
}
