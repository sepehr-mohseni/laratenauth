<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Feature;

use Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class TenantAuthFacadeTest extends TestCase
{
    protected TenantContextManager $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->app->make(TenantContextManager::class);
    }

    public function test_facade_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $this->context->setTenant($tenant);

        $this->assertEquals($tenant->id, TenantAuth::tenant()->id);
    }

    public function test_facade_has_tenant(): void
    {
        $this->assertFalse(TenantAuth::hasTenant());

        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $this->context->setTenant($tenant);

        $this->assertTrue(TenantAuth::hasTenant());
    }

    public function test_facade_execute_in_tenant(): void
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'is_active' => true]);

        $this->context->setTenant($tenant1);

        $result = TenantAuth::executeInTenant($tenant2, function ($tenant) {
            return $tenant->name;
        });

        $this->assertEquals('Tenant 2', $result);
        $this->assertEquals($tenant1->id, TenantAuth::tenant()->id);
    }

    public function test_facade_has_access(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->joinTenant($tenant);
        $this->actingAs($user);

        $this->assertTrue(TenantAuth::hasAccess($tenant));

        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'is_active' => true]);
        $this->assertFalse(TenantAuth::hasAccess($tenant2));
    }
}
