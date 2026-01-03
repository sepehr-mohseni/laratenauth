<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantNotResolvedException;
use Sepehr_Mohseni\LaraTenAuth\Middleware\IdentifyTenant;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class IdentifyTenantMiddlewareTest extends TestCase
{
    protected TenantContextManager $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->app->make(TenantContextManager::class);
    }

    public function test_identifies_tenant_from_request(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'is_active' => true,
        ]);

        config(['laratenauth.resolution.strategies' => ['header']]);
        config(['laratenauth.resolution.header_name' => 'X-Tenant-ID']);

        $middleware = new IdentifyTenant($this->context);

        $request = Request::create('http://example.com/api/users');
        $request->headers->set('X-Tenant-ID', (string) $tenant->id);

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->context->hasTenant());
        $this->assertEquals($tenant->id, $this->context->getTenant()->id);
    }

    public function test_throws_when_required_and_not_found(): void
    {
        config(['laratenauth.resolution.strategies' => ['header']]);

        $middleware = new IdentifyTenant($this->context);

        $request = Request::create('http://example.com/api/users');

        $this->expectException(TenantNotResolvedException::class);

        $middleware->handle($request, function ($req) {
            return new Response('OK');
        }, true);
    }

    public function test_continues_when_optional_and_not_found(): void
    {
        config(['laratenauth.resolution.strategies' => ['header']]);

        $middleware = new IdentifyTenant($this->context);

        $request = Request::create('http://example.com/api/users');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        }, false);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($this->context->hasTenant());
    }
}
