<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantAccessDeniedException;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantAuthManager;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class TenantAuthManagerTest extends TestCase
{
    protected TenantAuthManager $authManager;
    protected TenantContextManager $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authManager = $this->app->make(TenantAuthManager::class);
        $this->context = $this->app->make(TenantContextManager::class);
    }

    public function test_tenant_returns_current_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $this->context->setTenant($tenant);

        $current = $this->authManager->tenant();

        $this->assertEquals($tenant->id, $current->id);
    }

    public function test_tenant_returns_null_when_no_tenant(): void
    {
        $this->assertNull($this->authManager->tenant());
    }

    public function test_has_tenant(): void
    {
        $this->assertFalse($this->authManager->hasTenant());

        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $this->context->setTenant($tenant);

        $this->assertTrue($this->authManager->hasTenant());
    }

    public function test_has_access_returns_false_when_no_user(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);

        $result = $this->authManager->hasAccess($tenant);

        $this->assertFalse($result);
    }

    public function test_has_access_checks_user_tenant_membership(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->joinTenant($tenant);

        $this->actingAs($user);

        $result = $this->authManager->hasAccess($tenant);

        $this->assertTrue($result);
    }

    public function test_execute_in_tenant(): void
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'is_active' => true]);

        $this->context->setTenant($tenant1);

        $result = $this->authManager->executeInTenant($tenant2, function ($tenant) {
            return $tenant->name;
        });

        $this->assertEquals('Tenant 2', $result);
        $this->assertEquals($tenant1->id, $this->authManager->tenant()->id);
    }

    public function test_switch_tenant_requires_authentication(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);

        $this->expectException(TenantAccessDeniedException::class);

        $this->authManager->switchTenant($tenant);
    }

    public function test_switch_tenant_requires_access(): void
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'is_active' => true]);

        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->joinTenant($tenant1);

        $this->actingAs($user);
        $this->context->setTenant($tenant1);

        $this->expectException(TenantAccessDeniedException::class);

        $this->authManager->switchTenant($tenant2);
    }

    public function test_impersonate_when_enabled(): void
    {
        config(['laratenauth.security.allow_impersonation' => true]);

        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);

        $user1 = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $user2 = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user1->joinTenant($tenant);
        $user2->joinTenant($tenant);

        $this->actingAs($user1);
        $this->context->setTenant($tenant);

        $this->authManager->impersonate($user2);

        $this->assertTrue($this->authManager->isImpersonating());
        $this->assertEquals($user2->id, $this->authManager->user()->id);
        $this->assertEquals($user1->id, $this->authManager->getOriginalUser()->id);
    }

    public function test_stop_impersonating(): void
    {
        config(['laratenauth.security.allow_impersonation' => true]);

        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);

        $user1 = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $user2 = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user1->joinTenant($tenant);
        $user2->joinTenant($tenant);

        $this->actingAs($user1);
        $this->context->setTenant($tenant);

        $this->authManager->impersonate($user2);
        $this->assertTrue($this->authManager->isImpersonating());

        $this->authManager->stopImpersonating();
        $this->assertFalse($this->authManager->isImpersonating());
    }

    public function test_impersonate_throws_when_disabled(): void
    {
        config(['laratenauth.security.allow_impersonation' => false]);

        $tenant = Tenant::create(['name' => 'Test Tenant', 'is_active' => true]);
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->joinTenant($tenant);
        $this->context->setTenant($tenant);

        $this->expectException(\RuntimeException::class);

        $this->authManager->impersonate($user);
    }

    public function test_set_tenant_by_model(): void
    {
        $tenant = Tenant::create(['name' => 'Model Tenant', 'is_active' => true]);

        $this->authManager->setTenant($tenant);

        $this->assertEquals($tenant->id, $this->authManager->tenant()->id);
    }

    public function test_clear_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Clear Tenant', 'is_active' => true]);
        $this->authManager->setTenant($tenant);

        $this->assertTrue($this->authManager->hasTenant());

        $this->authManager->clearTenant();

        $this->assertFalse($this->authManager->hasTenant());
    }

    public function test_execute_in_tenant_with_model(): void
    {
        $tenant = Tenant::create(['name' => 'Execute Tenant', 'is_active' => true]);

        $result = $this->authManager->executeInTenant($tenant, function () {
            return has_tenant();
        });

        $this->assertTrue($result);
    }

    public function test_user_returns_null_when_not_authenticated(): void
    {
        $this->assertNull($this->authManager->user());
    }

    public function test_switch_tenant_successfully(): void
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'is_active' => true]);

        $user = User::create([
            'name' => 'Multi Tenant User',
            'email' => 'multi@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->joinTenant($tenant1);
        $user->joinTenant($tenant2);

        $this->actingAs($user);
        $this->context->setTenant($tenant1);

        $this->authManager->switchTenant($tenant2);

        $this->assertEquals($tenant2->id, $this->authManager->tenant()->id);
    }

    public function test_get_original_user_returns_null_when_not_impersonating(): void
    {
        $this->assertNull($this->authManager->getOriginalUser());
    }

    public function test_is_impersonating_returns_false_by_default(): void
    {
        $this->assertFalse($this->authManager->isImpersonating());
    }
}
