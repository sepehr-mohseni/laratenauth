<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class HasTenantsTraitTest extends TestCase
{
    protected User $user;
    protected Tenant $tenant1;
    protected Tenant $tenant2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->tenant1 = Tenant::create(['name' => 'Tenant 1', 'slug' => 'tenant-1']);
        $this->tenant2 = Tenant::create(['name' => 'Tenant 2', 'slug' => 'tenant-2']);
    }

    public function test_can_join_tenant(): void
    {
        $this->user->joinTenant($this->tenant1, 'admin', ['manage-users']);

        $this->assertTrue($this->user->belongsToTenant($this->tenant1));
        $this->assertEquals('admin', $this->user->getTenantRole($this->tenant1));
        $this->assertEquals(['manage-users'], $this->user->getTenantPermissions($this->tenant1));
    }

    public function test_can_leave_tenant(): void
    {
        $this->user->joinTenant($this->tenant1);

        $this->assertTrue($this->user->belongsToTenant($this->tenant1));

        $this->user->leaveTenant($this->tenant1);

        $this->assertFalse($this->user->belongsToTenant($this->tenant1));
    }

    public function test_can_set_default_tenant(): void
    {
        $this->user->joinTenant($this->tenant1, null, [], true);
        $this->user->joinTenant($this->tenant2);

        $this->assertEquals($this->tenant1->id, $this->user->defaultTenant()->id);

        $this->user->setDefaultTenant($this->tenant2);

        $this->user->load('tenants');
        $this->assertEquals($this->tenant2->id, $this->user->defaultTenant()->id);
    }

    public function test_belongs_to_tenant_with_id(): void
    {
        $this->user->joinTenant($this->tenant1);

        $this->assertTrue($this->user->belongsToTenant($this->tenant1->id));
        $this->assertFalse($this->user->belongsToTenant($this->tenant2->id));
    }

    public function test_has_access_to_tenant(): void
    {
        $this->user->joinTenant($this->tenant1);

        $this->assertTrue($this->user->hasAccessToTenant($this->tenant1));
        $this->assertFalse($this->user->hasAccessToTenant($this->tenant2));
    }

    public function test_can_set_tenant_role(): void
    {
        $this->user->joinTenant($this->tenant1, 'member');

        $this->assertEquals('member', $this->user->getTenantRole($this->tenant1));

        $this->user->setTenantRole($this->tenant1, 'admin');

        $this->assertEquals('admin', $this->user->getTenantRole($this->tenant1));
    }

    public function test_can_manage_tenant_permissions(): void
    {
        $this->user->joinTenant($this->tenant1);

        $this->user->addTenantPermission($this->tenant1, 'read');
        $this->assertTrue($this->user->hasTenantPermission($this->tenant1, 'read'));

        $this->user->addTenantPermission($this->tenant1, 'write');
        $this->assertEquals(['read', 'write'], $this->user->getTenantPermissions($this->tenant1));

        $this->user->removeTenantPermission($this->tenant1, 'read');
        $this->assertFalse($this->user->hasTenantPermission($this->tenant1, 'read'));
        $this->assertTrue($this->user->hasTenantPermission($this->tenant1, 'write'));
    }

    public function test_wildcard_permission(): void
    {
        $this->user->joinTenant($this->tenant1, null, ['*']);

        $this->assertTrue($this->user->hasTenantPermission($this->tenant1, 'anything'));
        $this->assertTrue($this->user->hasTenantPermission($this->tenant1, 'read'));
        $this->assertTrue($this->user->hasTenantPermission($this->tenant1, 'delete'));
    }

    public function test_has_tenant_role(): void
    {
        $this->user->joinTenant($this->tenant1, 'admin');

        $this->assertTrue($this->user->hasTenantRole($this->tenant1, 'admin'));
        $this->assertFalse($this->user->hasTenantRole($this->tenant1, 'member'));
    }

    public function test_in_tenant_scope(): void
    {
        $user2 = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->user->joinTenant($this->tenant1);
        $user2->joinTenant($this->tenant2);

        $usersInTenant1 = User::inTenant($this->tenant1)->get();

        $this->assertCount(1, $usersInTenant1);
        $this->assertEquals($this->user->id, $usersInTenant1->first()->id);
    }

    public function test_with_tenant_role_scope(): void
    {
        $user2 = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->user->joinTenant($this->tenant1, 'admin');
        $user2->joinTenant($this->tenant1, 'member');

        $admins = User::withTenantRole($this->tenant1, 'admin')->get();

        $this->assertCount(1, $admins);
        $this->assertEquals($this->user->id, $admins->first()->id);
    }
}
