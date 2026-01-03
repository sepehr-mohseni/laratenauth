<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Sepehr_Mohseni\LaraTenAuth\Guards\TenantTokenGuard;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Models\TenantToken;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class TenantTokenGuardTest extends TestCase
{
    protected TenantTokenGuard $guard;
    protected EloquentUserProvider $provider;
    protected Request $request;
    protected TenantContextManager $tenantContext;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new EloquentUserProvider(
            $this->app['hash'],
            User::class
        );

        $this->request = Request::create('/api/test');

        $this->guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $this->request,
            TenantToken::class
        );

        // Create tenant
        $this->tenant = Tenant::create([
            'name' => 'Token Test Tenant',
            'slug' => 'token-test-tenant',
            'subdomain' => 'token-test',
            'is_active' => true,
        ]);

        // Get TenantContextManager from container
        $this->tenantContext = $this->app->make(TenantContextManager::class);
        $this->guard->setTenantContext($this->tenantContext);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_guard_can_be_instantiated(): void
    {
        $this->assertInstanceOf(TenantTokenGuard::class, $this->guard);
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

    public function test_set_request_returns_self(): void
    {
        $result = $this->guard->setRequest($this->request);
        $this->assertSame($this->guard, $result);
    }

    public function test_user_returns_null_without_token(): void
    {
        $this->assertNull($this->guard->user());
    }

    public function test_validate_returns_false_without_token(): void
    {
        $result = $this->guard->validate([]);
        $this->assertFalse($result);
    }

    public function test_validate_returns_false_with_empty_token(): void
    {
        $result = $this->guard->validate(['api_token' => '']);
        $this->assertFalse($result);
    }

    public function test_get_token_from_query_string(): void
    {
        $request = Request::create('/api/test?api_token=test-token');
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );

        $this->assertEquals('test-token', $guard->getTokenForRequest());
    }

    public function test_get_token_from_bearer_header(): void
    {
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer test-bearer-token');
        
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );

        $this->assertEquals('test-bearer-token', $guard->getTokenForRequest());
    }

    public function test_user_returns_null_with_invalid_token(): void
    {
        $request = Request::create('/api/test?api_token=invalid-token');
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );

        $this->assertNull($guard->user());
    }

    public function test_user_returns_null_with_expired_token(): void
    {
        // Create user and token
        $user = User::create([
            'name' => 'Token Test User',
            'email' => 'tokentest@example.com',
            'password' => Hash::make('password'),
        ]);

        $plainToken = 'test-plain-token-expired';
        $hashedToken = hash('sha256', $plainToken);

        TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Expired Token',
            'token' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => now()->subDay(), // Expired
        ]);

        $request = Request::create('/api/test?api_token=' . $plainToken);
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );

        $this->assertNull($guard->user());
    }

    public function test_token_without_tenant_context_returns_user(): void
    {
        // Create user
        $user = User::create([
            'name' => 'No Context User',
            'email' => 'nocontext@example.com',
            'password' => Hash::make('password'),
        ]);

        $plainToken = 'no-context-token';
        $hashedToken = hash('sha256', $plainToken);

        TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'No Context Token',
            'token' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $request = Request::create('/api/test?api_token=' . $plainToken);
        
        // Guard without tenant context
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );

        $authenticatedUser = $guard->user();
        
        $this->assertNotNull($authenticatedUser);
        $this->assertEquals($user->id, $authenticatedUser->id);
    }

    public function test_user_with_valid_token_and_tenant(): void
    {
        // Create user and attach to tenant
        $user = User::create([
            'name' => 'Valid Token User',
            'email' => 'validtoken@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->attachToTenant($this->tenant);

        $plainToken = 'valid-tenant-token';
        $hashedToken = hash('sha256', $plainToken);

        TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Valid Token',
            'token' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $request = Request::create('/api/test?api_token=' . $plainToken);
        
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );
        $guard->setTenantContext($this->tenantContext);
        $this->tenantContext->setTenant($this->tenant);

        $authenticatedUser = $guard->user();
        
        $this->assertNotNull($authenticatedUser);
        $this->assertEquals($user->id, $authenticatedUser->id);
    }

    public function test_user_returns_null_when_not_in_tenant(): void
    {
        // Create user but don't attach to tenant
        $user = User::create([
            'name' => 'Wrong Tenant User',
            'email' => 'wrongtenant@example.com',
            'password' => Hash::make('password'),
        ]);

        $plainToken = 'wrong-tenant-token';
        $hashedToken = hash('sha256', $plainToken);

        TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Wrong Tenant Token',
            'token' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $request = Request::create('/api/test?api_token=' . $plainToken);
        
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );
        $guard->setTenantContext($this->tenantContext);
        $this->tenantContext->setTenant($this->tenant);

        // User not attached to tenant, should return null
        $this->assertNull($guard->user());
    }

    public function test_validate_returns_true_with_valid_token(): void
    {
        $user = User::create([
            'name' => 'Validate User',
            'email' => 'validate@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->attachToTenant($this->tenant);

        $plainToken = 'validate-token';
        $hashedToken = hash('sha256', $plainToken);

        TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Validate Token',
            'token' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $this->guard->setTenantContext($this->tenantContext);
        $this->tenantContext->setTenant($this->tenant);

        $result = $this->guard->validate(['api_token' => $plainToken]);
        $this->assertTrue($result);
    }

    public function test_get_token_from_input(): void
    {
        $request = Request::create('/api/test', 'POST', ['api_token' => 'post-token']);
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );

        $this->assertEquals('post-token', $guard->getTokenForRequest());
    }

    public function test_get_token_from_basic_auth_password(): void
    {
        // Create request with Basic auth
        $request = Request::create('/api/test');
        $request->headers->set('PHP_AUTH_PW', 'basic-auth-token');
        
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );

        $this->assertEquals('basic-auth-token', $guard->getTokenForRequest());
    }

    public function test_user_returns_cached_user(): void
    {
        $user = User::create([
            'name' => 'Cached User',
            'email' => 'cached@example.com',
            'password' => Hash::make('password'),
        ]);

        $plainToken = 'cached-token';
        $hashedToken = hash('sha256', $plainToken);

        TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Cached Token',
            'token' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $request = Request::create('/api/test?api_token=' . $plainToken);
        
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );

        // First call
        $firstUser = $guard->user();
        // Second call should return cached user
        $secondUser = $guard->user();

        $this->assertSame($firstUser->id, $secondUser->id);
    }

    public function test_token_with_pipe_format(): void
    {
        $user = User::create([
            'name' => 'Pipe Format User',
            'email' => 'pipeformat@example.com',
            'password' => Hash::make('password'),
        ]);

        $plainToken = 'piped-token-part';
        $hashedToken = hash('sha256', $plainToken);

        $token = TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Pipe Format Token',
            'token' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        // Use pipe format: id|plain_token
        $pipeToken = $token->id . '|' . $plainToken;

        $request = Request::create('/api/test?api_token=' . $pipeToken);
        
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );

        $authenticatedUser = $guard->user();
        
        $this->assertNotNull($authenticatedUser);
        $this->assertEquals($user->id, $authenticatedUser->id);
    }

    public function test_wrong_tenant_token_throws_exception(): void
    {
        // Create user and token for this tenant
        $user = User::create([
            'name' => 'Wrong Tenant Token User',
            'email' => 'wrongtokentenant@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->attachToTenant($this->tenant);

        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'subdomain' => 'other',
            'is_active' => true,
        ]);

        $plainToken = 'wrong-tenant-token-check';
        $hashedToken = hash('sha256', $plainToken);

        // Token is for $this->tenant but we'll set context to $otherTenant
        TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Wrong Tenant Token Check',
            'token' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $request = Request::create('/api/test?api_token=' . $plainToken);
        
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );
        $guard->setTenantContext($this->tenantContext);
        $guard->setCrossTenantAuth(false);
        $this->tenantContext->setTenant($otherTenant);

        $this->expectException(\Sepehr_Mohseni\LaraTenAuth\Exceptions\InvalidTenantTokenException::class);
        $guard->user();
    }

    public function test_cross_tenant_allowed_with_different_tenant(): void
    {
        // Create user and attach to tenant
        $user = User::create([
            'name' => 'Cross Tenant User',
            'email' => 'crosstenant@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->attachToTenant($this->tenant);

        $otherTenant = Tenant::create([
            'name' => 'Another Tenant',
            'slug' => 'another-tenant',
            'subdomain' => 'another',
            'is_active' => true,
        ]);
        $user->attachToTenant($otherTenant);

        $plainToken = 'cross-tenant-token';
        $hashedToken = hash('sha256', $plainToken);

        TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Cross Tenant Token',
            'token' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $request = Request::create('/api/test?api_token=' . $plainToken);
        
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );
        $guard->setTenantContext($this->tenantContext);
        $guard->setCrossTenantAuth(true);
        $this->tenantContext->setTenant($otherTenant);

        // Should succeed because cross-tenant is allowed
        $authenticatedUser = $guard->user();
        
        $this->assertNotNull($authenticatedUser);
        $this->assertEquals($user->id, $authenticatedUser->id);
    }

    public function test_revoked_token_returns_null(): void
    {
        $user = User::create([
            'name' => 'Revoked Token User',
            'email' => 'revoked@example.com',
            'password' => Hash::make('password'),
        ]);

        $plainToken = 'revoked-token';
        $hashedToken = hash('sha256', $plainToken);

        TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Revoked Token',
            'token' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
            'revoked' => true, // Token is revoked
        ]);

        $request = Request::create('/api/test?api_token=' . $plainToken);
        
        $guard = new TenantTokenGuard(
            'tenant-token',
            $this->provider,
            $request,
            TenantToken::class
        );

        $this->assertNull($guard->user());
    }
}
