<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class TenantTest extends TestCase
{
    public function test_can_create_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'domain' => 'test.example.com',
            'subdomain' => 'test',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $this->assertTrue($tenant->isActive());
    }

    public function test_can_activate_and_deactivate_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'is_active' => false,
        ]);

        $this->assertFalse($tenant->isActive());

        $tenant->activate();
        $tenant->refresh();
        $this->assertTrue($tenant->isActive());

        $tenant->deactivate();
        $tenant->refresh();
        $this->assertFalse($tenant->isActive());
    }

    public function test_can_set_and_get_settings(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
        ]);

        $tenant->setSetting('theme.color', 'blue');

        $this->assertEquals('blue', $tenant->getSetting('theme.color'));
        $this->assertEquals('default', $tenant->getSetting('theme.unknown', 'default'));
    }

    public function test_can_set_and_get_metadata(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
        ]);

        $tenant->setMeta('plan', 'premium');

        $this->assertEquals('premium', $tenant->getMeta('plan'));
        $this->assertNull($tenant->getMeta('unknown'));
    }

    public function test_can_add_and_remove_user(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant']);
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $tenant->addUser($user, 'admin', ['manage-users']);

        $this->assertTrue($tenant->hasUser($user));
        $this->assertEquals('admin', $tenant->getUserRole($user));
        $this->assertEquals(['manage-users'], $tenant->getUserPermissions($user));

        $tenant->removeUser($user);
        $tenant->load('users');

        $this->assertFalse($tenant->hasUser($user));
    }

    public function test_generate_unique_slug(): void
    {
        $slug1 = Tenant::generateSlug('Test Company');
        $this->assertEquals('test-company', $slug1);

        Tenant::create(['name' => 'Test Company', 'slug' => 'test-company']);

        $slug2 = Tenant::generateSlug('Test Company');
        $this->assertEquals('test-company-1', $slug2);
    }

    public function test_active_scope(): void
    {
        Tenant::create(['name' => 'Active Tenant', 'is_active' => true]);
        Tenant::create(['name' => 'Inactive Tenant', 'is_active' => false]);

        $activeTenants = Tenant::active()->get();

        $this->assertCount(1, $activeTenants);
        $this->assertEquals('Active Tenant', $activeTenants->first()->name);
    }

    public function test_inactive_scope(): void
    {
        Tenant::create(['name' => 'Active Tenant', 'is_active' => true]);
        Tenant::create(['name' => 'Inactive Tenant', 'is_active' => false]);

        $inactiveTenants = Tenant::inactive()->get();

        $this->assertCount(1, $inactiveTenants);
        $this->assertEquals('Inactive Tenant', $inactiveTenants->first()->name);
    }

    public function test_by_domain_scope(): void
    {
        Tenant::create(['name' => 'Tenant A', 'domain' => 'tenant-a.com']);
        Tenant::create(['name' => 'Tenant B', 'domain' => 'tenant-b.com']);

        $tenant = Tenant::byDomain('tenant-a.com')->first();

        $this->assertNotNull($tenant);
        $this->assertEquals('Tenant A', $tenant->name);
    }

    public function test_by_subdomain_scope(): void
    {
        Tenant::create(['name' => 'Tenant A', 'subdomain' => 'alpha']);
        Tenant::create(['name' => 'Tenant B', 'subdomain' => 'beta']);

        $tenant = Tenant::bySubdomain('alpha')->first();

        $this->assertNotNull($tenant);
        $this->assertEquals('Tenant A', $tenant->name);
    }

    public function test_by_slug_scope(): void
    {
        Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $tenant = Tenant::bySlug('tenant-a')->first();

        $this->assertNotNull($tenant);
        $this->assertEquals('Tenant A', $tenant->name);
    }
}
