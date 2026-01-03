<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Illuminate\Http\Request;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantIdentified;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantSwitched;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantNotResolvedException;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class TenantContextManagerTest extends TestCase
{
    protected TenantContextManager $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->app->make(TenantContextManager::class);
    }

    public function test_can_set_and_get_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);

        $this->context->setTenant($tenant);

        $this->assertNotNull($this->context->getTenant());
        $this->assertEquals($tenant->id, $this->context->getTenant()->id);
    }

    public function test_has_tenant(): void
    {
        $this->assertFalse($this->context->hasTenant());

        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $this->context->setTenant($tenant);

        $this->assertTrue($this->context->hasTenant());
    }

    public function test_clear_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $this->context->setTenant($tenant);

        $this->assertTrue($this->context->hasTenant());

        $this->context->clearTenant();

        $this->assertFalse($this->context->hasTenant());
        $this->assertNull($this->context->getTenant());
    }

    public function test_get_tenant_or_fail_throws_when_no_tenant(): void
    {
        $this->expectException(TenantNotResolvedException::class);

        $this->context->getTenantOrFail();
    }

    public function test_get_tenant_or_fail_returns_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $this->context->setTenant($tenant);

        $resolved = $this->context->getTenantOrFail();

        $this->assertEquals($tenant->id, $resolved->id);
    }

    public function test_switch_tenant_dispatches_event(): void
    {
        Event::fake([TenantSwitched::class]);

        // Rebuild the context manager to use the fake event dispatcher
        $this->app->forgetInstance('tenant.context');
        $context = $this->app->make('tenant.context');

        $tenant1 = Tenant::create(['name' => 'Tenant 1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'is_active' => true]);

        $context->setTenant($tenant1);
        $context->switchTenant($tenant2);

        Event::assertDispatched(TenantSwitched::class, function ($event) use ($tenant1, $tenant2) {
            return $event->previousTenant->id === $tenant1->id
                && $event->newTenant->id === $tenant2->id;
        });
    }

    public function test_get_previous_tenant(): void
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'is_active' => true]);

        $this->context->setTenant($tenant1);
        $this->context->setTenant($tenant2);

        $this->assertEquals($tenant1->id, $this->context->getPreviousTenant()->id);
    }

    public function test_execute_in_tenant(): void
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'is_active' => true]);

        $this->context->setTenant($tenant1);

        $result = $this->context->executeInTenant($tenant2, function ($tenant) {
            return $tenant->name;
        });

        $this->assertEquals('Tenant 2', $result);
        // Context should be restored
        $this->assertEquals($tenant1->id, $this->context->getTenant()->id);
    }

    public function test_identify_dispatches_event(): void
    {
        Event::fake([TenantIdentified::class]);

        // Rebuild the context manager to use the fake event dispatcher
        $this->app->forgetInstance('tenant.context');
        $context = $this->app->make('tenant.context');

        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);

        config(['laratenauth.resolution.strategies' => ['header']]);
        config(['laratenauth.resolution.header_name' => 'X-Tenant-ID']);

        $request = Request::create('http://example.com/api/users');
        $request->headers->set('X-Tenant-ID', (string) $tenant->id);

        $resolved = $context->identify($request);

        $this->assertNotNull($resolved);
        Event::assertDispatched(TenantIdentified::class, function ($event) use ($tenant) {
            return $event->tenant->id === $tenant->id;
        });
    }
}
