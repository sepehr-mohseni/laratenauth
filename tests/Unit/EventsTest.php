<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Sepehr_Mohseni\LaraTenAuth\Events\TenantAuthenticated;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantAccessDenied;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantIdentified;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantSwitched;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantTokenCreated;
use Sepehr_Mohseni\LaraTenAuth\Events\TenantTokenRevoked;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Models\TenantToken;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class EventsTest extends TestCase
{
    protected User $user;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        
        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
    }

    public function test_tenant_authenticated_event(): void
    {
        $event = new TenantAuthenticated($this->tenant, $this->user);

        $this->assertSame($this->tenant, $event->tenant);
        $this->assertSame($this->user, $event->user);
    }

    public function test_tenant_access_denied_event(): void
    {
        $event = new TenantAccessDenied($this->user, $this->tenant);

        $this->assertSame($this->tenant, $event->tenant);
        $this->assertSame($this->user, $event->user);
    }

    public function test_tenant_identified_event(): void
    {
        $event = new TenantIdentified($this->tenant);

        $this->assertSame($this->tenant, $event->tenant);
    }

    public function test_tenant_switched_event(): void
    {
        $newTenant = Tenant::create(['name' => 'New Tenant', 'slug' => 'new-tenant']);

        $event = new TenantSwitched($this->tenant, $newTenant);

        $this->assertSame($this->tenant, $event->previousTenant);
        $this->assertSame($newTenant, $event->newTenant);
    }

    public function test_tenant_switched_event_with_null_previous(): void
    {
        $event = new TenantSwitched(null, $this->tenant);

        $this->assertNull($event->previousTenant);
        $this->assertSame($this->tenant, $event->newTenant);
    }

    public function test_tenant_token_created_event(): void
    {
        $token = TenantToken::create([
            'tokenable_type' => User::class,
            'tokenable_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Token',
            'token' => hash('sha256', 'test-token'),
            'abilities' => ['*'],
        ]);

        $event = new TenantTokenCreated($token);

        $this->assertSame($token, $event->token);
    }

    public function test_tenant_token_revoked_event(): void
    {
        $token = TenantToken::create([
            'tokenable_type' => User::class,
            'tokenable_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Token',
            'token' => hash('sha256', 'test-token-2'),
            'abilities' => ['*'],
        ]);

        $event = new TenantTokenRevoked($token);

        $this->assertSame($token, $event->token);
    }
}
