<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Illuminate\Http\Request;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantResolver;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class TenantResolverTest extends TestCase
{
    protected TenantResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = $this->app->make(TenantResolver::class);
    }

    public function test_can_resolve_tenant_by_subdomain(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'subdomain' => 'acme',
            'is_active' => true,
        ]);

        config(['laratenauth.resolution.strategies' => ['subdomain']]);
        config(['laratenauth.resolution.central_domains' => ['example.com']]);

        $request = Request::create('http://acme.example.com/api/users');

        $resolved = $this->resolver->resolve($request);

        $this->assertNotNull($resolved);
        $this->assertEquals($tenant->id, $resolved->id);
    }

    public function test_can_resolve_tenant_by_domain(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'custom-domain.com',
            'is_active' => true,
        ]);

        config(['laratenauth.resolution.strategies' => ['domain']]);

        $request = Request::create('http://custom-domain.com/api/users');

        $resolved = $this->resolver->resolve($request);

        $this->assertNotNull($resolved);
        $this->assertEquals($tenant->id, $resolved->id);
    }

    public function test_can_resolve_tenant_by_header(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'is_active' => true,
        ]);

        config(['laratenauth.resolution.strategies' => ['header']]);
        config(['laratenauth.resolution.header_name' => 'X-Tenant-ID']);

        $request = Request::create('http://example.com/api/users');
        $request->headers->set('X-Tenant-ID', (string) $tenant->id);

        $resolved = $this->resolver->resolve($request);

        $this->assertNotNull($resolved);
        $this->assertEquals($tenant->id, $resolved->id);
    }

    public function test_returns_null_for_inactive_tenant(): void
    {
        Tenant::create([
            'name' => 'Inactive Tenant',
            'subdomain' => 'inactive',
            'is_active' => false,
        ]);

        config(['laratenauth.resolution.strategies' => ['subdomain']]);
        config(['laratenauth.resolution.central_domains' => ['example.com']]);

        $request = Request::create('http://inactive.example.com/api/users');

        $resolved = $this->resolver->resolve($request);

        $this->assertNull($resolved);
    }

    public function test_returns_null_when_tenant_not_found(): void
    {
        config(['laratenauth.resolution.strategies' => ['subdomain']]);
        config(['laratenauth.resolution.central_domains' => ['example.com']]);

        $request = Request::create('http://nonexistent.example.com/api/users');

        $resolved = $this->resolver->resolve($request);

        $this->assertNull($resolved);
    }

    public function test_can_resolve_tenant_by_id(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'is_active' => true,
        ]);

        $resolved = $this->resolver->resolveById($tenant->id);

        $this->assertNotNull($resolved);
        $this->assertEquals($tenant->id, $resolved->id);
    }

    public function test_resolve_by_id_returns_null_for_invalid_id(): void
    {
        $resolved = $this->resolver->resolveById(999999);

        $this->assertNull($resolved);
    }

    public function test_can_register_custom_strategy(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'custom-slug',
            'is_active' => true,
        ]);

        $this->resolver->registerStrategy('custom', function (Request $request) {
            $slug = $request->header('X-Custom-Slug');
            if ($slug) {
                return Tenant::where('slug', $slug)->where('is_active', true)->first();
            }
            return null;
        });

        config(['laratenauth.resolution.strategies' => ['custom']]);

        $request = Request::create('http://example.com/api/users');
        $request->headers->set('X-Custom-Slug', 'custom-slug');

        $resolved = $this->resolver->resolve($request);

        $this->assertNotNull($resolved);
        $this->assertEquals($tenant->id, $resolved->id);
    }

    public function test_tries_multiple_strategies(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'is_active' => true,
        ]);

        config(['laratenauth.resolution.strategies' => ['subdomain', 'domain', 'header']]);
        config(['laratenauth.resolution.central_domains' => ['example.com']]);
        config(['laratenauth.resolution.header_name' => 'X-Tenant-ID']);

        // Subdomain won't match, but header should
        $request = Request::create('http://example.com/api/users');
        $request->headers->set('X-Tenant-ID', (string) $tenant->id);

        $resolved = $this->resolver->resolve($request);

        $this->assertNotNull($resolved);
        $this->assertEquals($tenant->id, $resolved->id);
    }

    public function test_clear_cache_does_not_throw(): void
    {
        $this->resolver->clearCache();
        $this->assertTrue(true);
    }

    public function test_resolve_by_id_returns_null_for_inactive_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Inactive Tenant',
            'is_active' => false,
        ]);

        $resolved = $this->resolver->resolveById($tenant->id);

        $this->assertNull($resolved);
    }

    public function test_subdomain_strategy_returns_null_for_central_domain(): void
    {
        config(['laratenauth.resolution.strategies' => ['subdomain']]);
        config(['laratenauth.resolution.central_domains' => ['example.com']]);

        // Request to central domain directly (no subdomain)
        $request = Request::create('http://example.com/api/users');

        $resolved = $this->resolver->resolve($request);

        $this->assertNull($resolved);
    }

    public function test_unregistered_custom_strategy_returns_null(): void
    {
        config(['laratenauth.resolution.strategies' => ['unknown_strategy']]);

        $request = Request::create('http://example.com/api/users');

        $resolved = $this->resolver->resolve($request);

        $this->assertNull($resolved);
    }

    public function test_domain_strategy_returns_null_when_not_found(): void
    {
        config(['laratenauth.resolution.strategies' => ['domain']]);

        $request = Request::create('http://nonexistent-domain.com/api/users');

        $resolved = $this->resolver->resolve($request);

        $this->assertNull($resolved);
    }

    public function test_header_strategy_returns_null_without_header(): void
    {
        config(['laratenauth.resolution.strategies' => ['header']]);
        config(['laratenauth.resolution.header_name' => 'X-Tenant-ID']);

        $request = Request::create('http://example.com/api/users');
        // No header set

        $resolved = $this->resolver->resolve($request);

        $this->assertNull($resolved);
    }

    public function test_caching_enabled_stores_tenant(): void
    {
        config(['laratenauth.resolution.cache_enabled' => true]);
        config(['laratenauth.resolution.cache_ttl' => 3600]);

        $tenant = Tenant::create([
            'name' => 'Cached Tenant',
            'subdomain' => 'cached',
            'is_active' => true,
        ]);

        config(['laratenauth.resolution.strategies' => ['subdomain']]);
        config(['laratenauth.resolution.central_domains' => ['example.com']]);

        $resolver = $this->app->make(TenantResolver::class);

        $request = Request::create('http://cached.example.com/api/users');

        // First resolution - should cache
        $resolved1 = $resolver->resolve($request);
        $this->assertNotNull($resolved1);

        // Second resolution - should use cache
        $resolved2 = $resolver->resolve($request);
        $this->assertNotNull($resolved2);
        $this->assertEquals($resolved1->id, $resolved2->id);
    }
}
