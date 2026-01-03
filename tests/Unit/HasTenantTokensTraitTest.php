<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Illuminate\Support\Carbon;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Models\TenantToken;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class HasTenantTokensTraitTest extends TestCase
{
    protected User $user;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->tenant = Tenant::create(['name' => 'Test Tenant']);
    }

    public function test_can_create_tenant_token(): void
    {
        $result = $this->user->createTenantToken(
            'test-token',
            ['*'],
            $this->tenant
        );

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('plainTextToken', $result);
        $this->assertInstanceOf(TenantToken::class, $result['token']);
    }

    public function test_can_get_token_by_name(): void
    {
        $this->user->createTenantToken('api-token');

        $token = $this->user->getTenantToken('api-token');

        $this->assertNotNull($token);
        $this->assertEquals('api-token', $token->name);
    }

    public function test_can_get_tokens_for_tenant(): void
    {
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);

        $this->user->createTenantToken('token-1', ['*'], $this->tenant);
        $this->user->createTenantToken('token-2', ['*'], $tenant2);
        $this->user->createTenantToken('token-3', ['*'], $this->tenant);

        $tokens = $this->user->getTenantTokensForTenant($this->tenant);

        $this->assertCount(2, $tokens);
    }

    public function test_can_revoke_token_by_name(): void
    {
        $this->user->createTenantToken('api-token');

        $this->assertTrue($this->user->revokeTenantToken('api-token'));

        $token = $this->user->getTenantToken('api-token');
        $this->assertTrue($token->isRevoked());
    }

    public function test_can_revoke_all_tokens(): void
    {
        $this->user->createTenantToken('token-1');
        $this->user->createTenantToken('token-2');
        $this->user->createTenantToken('token-3');

        $count = $this->user->revokeAllTenantTokens();

        $this->assertEquals(3, $count);
        $this->assertFalse($this->user->hasValidTenantTokens());
    }

    public function test_can_revoke_tokens_for_tenant(): void
    {
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);

        $this->user->createTenantToken('token-1', ['*'], $this->tenant);
        $this->user->createTenantToken('token-2', ['*'], $tenant2);
        $this->user->createTenantToken('token-3', ['*'], $this->tenant);

        $count = $this->user->revokeTenantTokensForTenant($this->tenant);

        $this->assertEquals(2, $count);
        $this->assertFalse($this->user->hasValidTenantTokensForTenant($this->tenant));
        $this->assertTrue($this->user->hasValidTenantTokensForTenant($tenant2));
    }

    public function test_can_delete_expired_tokens(): void
    {
        $this->user->createTenantToken('valid-token');
        $this->user->createTenantToken('expired-token', ['*'], null, Carbon::now()->subDay());

        $this->assertEquals(2, $this->user->tenantTokens()->count());

        $deleted = $this->user->deleteExpiredTenantTokens();

        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, $this->user->tenantTokens()->count());
    }

    public function test_has_valid_tenant_tokens(): void
    {
        $this->assertFalse($this->user->hasValidTenantTokens());

        $this->user->createTenantToken('api-token');

        $this->assertTrue($this->user->hasValidTenantTokens());
    }

    public function test_can_update_token_abilities(): void
    {
        $this->user->createTenantToken('api-token', ['read']);

        $token = $this->user->getTenantToken('api-token');
        $this->assertTrue($token->can('read'));
        $this->assertFalse($token->can('write'));

        $this->user->updateTenantTokenAbilities('api-token', ['read', 'write']);

        $token->refresh();
        $this->assertTrue($token->can('read'));
        $this->assertTrue($token->can('write'));
    }

    public function test_current_tenant_token(): void
    {
        $result = $this->user->createTenantToken('api-token');
        $result['token']->markAsUsed();

        $current = $this->user->currentTenantToken();

        $this->assertNotNull($current);
        $this->assertEquals('api-token', $current->name);
    }
}
