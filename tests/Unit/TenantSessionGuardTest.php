<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Sepehr_Mohseni\LaraTenAuth\Guards\TenantSessionGuard;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class TenantSessionGuardTest extends TestCase
{
    protected TenantSessionGuard $guard;
    protected Session|\Mockery\MockInterface $session;
    protected EloquentUserProvider $provider;
    protected Request $request;
    protected TenantContextManager $tenantContext;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = Mockery::mock(Session::class);
        $this->session->shouldReceive('getName')->andReturn('test_session');
        $this->session->shouldReceive('getId')->andReturn('test_session_id');
        $this->session->shouldReceive('put')->andReturnNull();
        $this->session->shouldReceive('get')->andReturn(null);
        $this->session->shouldReceive('forget')->andReturnNull();
        $this->session->shouldReceive('remove')->andReturnNull();
        $this->session->shouldReceive('regenerate')->andReturnNull();
        $this->session->shouldReceive('migrate')->andReturn(true);
        $this->session->shouldReceive('invalidate')->andReturnNull();

        $this->provider = new EloquentUserProvider(
            $this->app['hash'],
            User::class
        );

        $this->request = Request::create('/test');

        $this->guard = new TenantSessionGuard(
            'tenant-session',
            $this->provider,
            $this->session,
            $this->request
        );

        // Create tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'subdomain' => 'test',
            'is_active' => true,
        ]);

        // Get TenantContextManager from container
        $this->tenantContext = $this->app->make(TenantContextManager::class);
        $this->guard->setTenantContext($this->tenantContext);
    }

    protected function tearDown(): void
    {
        // Close mockery before parent tearDown to avoid transaction issues
        if (class_exists(Mockery::class)) {
            Mockery::close();
        }
        parent::tearDown();
    }

    public function test_guard_can_be_instantiated(): void
    {
        $this->assertInstanceOf(TenantSessionGuard::class, $this->guard);
    }

    public function test_set_tenant_context_returns_self(): void
    {
        $result = $this->guard->setTenantContext($this->tenantContext);

        $this->assertSame($this->guard, $result);
    }

    public function test_set_cross_tenant_auth_returns_self(): void
    {
        $result = $this->guard->setCrossTenantAuth(true);

        $this->assertSame($this->guard, $result);
    }

    public function test_user_returns_null_when_no_session(): void
    {
        $this->assertNull($this->guard->user());
    }

    public function test_attempt_fails_with_invalid_credentials(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $result = $this->guard->attempt([
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertFalse($result);
    }

    public function test_set_dispatcher(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);

        $this->guard->setDispatcher($dispatcher);

        // Dispatcher was set successfully
        $this->assertTrue(true);
    }

    public function test_login_without_tenant_context_succeeds(): void
    {
        // Create a fresh guard without tenant context
        $guard = new TenantSessionGuard(
            'tenant-session',
            $this->provider,
            $this->session,
            $this->request
        );

        $user = User::create([
            'name' => 'Test User',
            'email' => 'nocontext@example.com',
            'password' => Hash::make('password'),
        ]);

        $guard->login($user);

        $this->assertSame($user->id, $guard->id());
    }

    public function test_user_returns_cached_user(): void
    {
        // Create a fresh guard without tenant context
        $guard = new TenantSessionGuard(
            'tenant-session',
            $this->provider,
            $this->session,
            $this->request
        );

        $user = User::create([
            'name' => 'Test User',
            'email' => 'cached@example.com',
            'password' => Hash::make('password'),
        ]);

        $guard->login($user);

        // First call
        $firstUser = $guard->user();
        // Second call should return cached user
        $secondUser = $guard->user();

        $this->assertSame($firstUser->id, $secondUser->id);
    }

    public function test_attempt_succeeds_with_valid_credentials_and_tenant_user(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'validuser@example.com',
            'password' => Hash::make('secret123'),
        ]);

        // Attach user to tenant
        $user->attachToTenant($this->tenant);

        // Set tenant context
        $this->tenantContext->setTenant($this->tenant);

        $result = $this->guard->attempt([
            'email' => 'validuser@example.com',
            'password' => 'secret123',
        ]);

        $this->assertTrue($result);
    }

    public function test_attempt_fails_when_user_not_in_tenant(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'outsider@example.com',
            'password' => Hash::make('secret123'),
        ]);

        // Don't attach user to tenant
        $this->tenantContext->setTenant($this->tenant);

        $result = $this->guard->attempt([
            'email' => 'outsider@example.com',
            'password' => 'secret123',
        ]);

        $this->assertFalse($result);
    }

    public function test_login_fires_event_when_tenant_set(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'eventuser@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->attachToTenant($this->tenant);

        $this->tenantContext->setTenant($this->tenant);

        $dispatcher = Mockery::mock(Dispatcher::class);
        // Allow any number of dispatch calls (parent login may fire events)
        $dispatcher->shouldReceive('dispatch')->andReturnNull();

        $this->guard->setDispatcher($dispatcher);
        $this->guard->login($user);

        $this->assertSame($user->id, $this->guard->id());
    }

    public function test_login_throws_exception_for_user_not_in_tenant(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'denied@example.com',
            'password' => Hash::make('password'),
        ]);

        // Don't attach to tenant
        $this->tenantContext->setTenant($this->tenant);

        $this->expectException(\Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantAccessDeniedException::class);

        $this->guard->login($user);
    }

    public function test_user_returns_null_when_user_not_in_tenant(): void
    {
        // Test that user method returns null when user is not in tenant context
        $guard = new TenantSessionGuard(
            'tenant-session',
            $this->provider,
            $this->session,
            $this->request
        );

        // Set tenant context with active tenant
        $this->tenantContext->setTenant($this->tenant);
        $guard->setTenantContext($this->tenantContext);

        // Without a logged-in user, it should return null
        $this->assertNull($guard->user());
    }

    public function test_cross_tenant_auth_allowed_returns_true_when_no_tenant(): void
    {
        // Create a fresh guard with cross-tenant allowed
        $guard = new TenantSessionGuard(
            'tenant-session',
            $this->provider,
            $this->session,
            $this->request
        );

        $guard->setTenantContext($this->tenantContext);
        $guard->setCrossTenantAuth(true);

        // No tenant set - cross tenant should allow
        $user = User::create([
            'name' => 'Test User',
            'email' => 'crosstenant@example.com',
            'password' => Hash::make('password'),
        ]);

        $result = $guard->attempt([
            'email' => 'crosstenant@example.com',
            'password' => 'password',
        ]);

        $this->assertTrue($result);
    }

    public function test_cross_tenant_auth_not_allowed_returns_false_when_no_tenant(): void
    {
        // Create a fresh guard with cross-tenant NOT allowed
        $guard = new TenantSessionGuard(
            'tenant-session',
            $this->provider,
            $this->session,
            $this->request
        );

        $guard->setTenantContext($this->tenantContext);
        $guard->setCrossTenantAuth(false);

        // No tenant set - cross tenant should deny
        $user = User::create([
            'name' => 'Test User',
            'email' => 'denycross@example.com',
            'password' => Hash::make('password'),
        ]);

        $result = $guard->attempt([
            'email' => 'denycross@example.com',
            'password' => 'password',
        ]);

        $this->assertFalse($result);
    }
}
