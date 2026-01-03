<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantNotResolvedException;
use Sepehr_Mohseni\LaraTenAuth\Middleware\EnsureTenantContext;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class EnsureTenantContextMiddlewareTest extends TestCase
{
    protected TenantContextManager $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->app->make(TenantContextManager::class);
    }

    public function test_passes_when_tenant_context_exists(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $this->context->setTenant($tenant);

        $middleware = new EnsureTenantContext($this->context);

        $request = Request::create('http://example.com/api/users');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_throws_when_no_tenant_context(): void
    {
        $middleware = new EnsureTenantContext($this->context);

        $request = Request::create('http://example.com/api/users');

        $this->expectException(TenantNotResolvedException::class);

        $middleware->handle($request, function ($req) {
            return new Response('OK');
        });
    }
}
