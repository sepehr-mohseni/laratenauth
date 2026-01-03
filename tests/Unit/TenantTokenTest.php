<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Illuminate\Support\Carbon;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Models\TenantToken;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class TenantTokenTest extends TestCase
{
    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant']);
        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_can_create_token(): void
    {
        $result = TenantToken::createToken(
            $this->user,
            'test-token',
            ['*'],
            $this->tenant->id
        );

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('plainTextToken', $result);
        $this->assertInstanceOf(TenantToken::class, $result['token']);
        $this->assertStringContainsString('|', $result['plainTextToken']);
    }

    public function test_token_is_valid(): void
    {
        $result = TenantToken::createToken($this->user, 'test-token');

        $this->assertTrue($result['token']->isValid());
        $this->assertFalse($result['token']->isExpired());
        $this->assertFalse($result['token']->isRevoked());
    }

    public function test_token_can_be_revoked(): void
    {
        $result = TenantToken::createToken($this->user, 'test-token');

        $result['token']->revoke();

        $this->assertTrue($result['token']->isRevoked());
        $this->assertFalse($result['token']->isValid());
    }

    public function test_token_can_be_restored(): void
    {
        $result = TenantToken::createToken($this->user, 'test-token');

        $result['token']->revoke();
        $this->assertTrue($result['token']->isRevoked());

        $result['token']->restore();
        $this->assertFalse($result['token']->isRevoked());
    }

    public function test_token_expiration(): void
    {
        $result = TenantToken::createToken(
            $this->user,
            'test-token',
            ['*'],
            null,
            Carbon::now()->subDay()
        );

        $this->assertTrue($result['token']->isExpired());
        $this->assertFalse($result['token']->isValid());
    }

    public function test_token_abilities(): void
    {
        $result = TenantToken::createToken(
            $this->user,
            'test-token',
            ['read', 'write']
        );

        $this->assertTrue($result['token']->can('read'));
        $this->assertTrue($result['token']->can('write'));
        $this->assertFalse($result['token']->can('delete'));
    }

    public function test_wildcard_ability(): void
    {
        $result = TenantToken::createToken(
            $this->user,
            'test-token',
            ['*']
        );

        $this->assertTrue($result['token']->can('read'));
        $this->assertTrue($result['token']->can('write'));
        $this->assertTrue($result['token']->can('delete'));
        $this->assertTrue($result['token']->can('anything'));
    }

    public function test_find_token_by_plain_text(): void
    {
        $result = TenantToken::createToken($this->user, 'test-token');

        $found = TenantToken::findToken($result['plainTextToken']);

        $this->assertNotNull($found);
        $this->assertEquals($result['token']->id, $found->id);
    }

    public function test_mark_as_used(): void
    {
        $result = TenantToken::createToken($this->user, 'test-token');

        $this->assertNull($result['token']->last_used_at);

        $result['token']->markAsUsed();
        $result['token']->refresh();

        $this->assertNotNull($result['token']->last_used_at);
    }

    public function test_extend_expiration(): void
    {
        $result = TenantToken::createToken(
            $this->user,
            'test-token',
            ['*'],
            null,
            Carbon::now()->addDays(5)
        );

        $originalExpiration = $result['token']->expires_at;

        $result['token']->extendExpiration(10);
        $result['token']->refresh();

        $this->assertTrue($result['token']->expires_at->gt($originalExpiration));
    }

    public function test_valid_scope(): void
    {
        TenantToken::createToken($this->user, 'valid-token');
        $revokedResult = TenantToken::createToken($this->user, 'revoked-token');
        $revokedResult['token']->revoke();

        $expiredResult = TenantToken::createToken(
            $this->user,
            'expired-token',
            ['*'],
            null,
            Carbon::now()->subDay()
        );

        $validTokens = TenantToken::valid()->get();

        $this->assertCount(1, $validTokens);
        $this->assertEquals('valid-token', $validTokens->first()->name);
    }

    public function test_for_tenant_scope(): void
    {
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);

        TenantToken::createToken($this->user, 'token-1', ['*'], $this->tenant->id);
        TenantToken::createToken($this->user, 'token-2', ['*'], $tenant2->id);
        TenantToken::createToken($this->user, 'global-token', ['*'], null);

        $tenant1Tokens = TenantToken::forTenant($this->tenant->id)->get();

        $this->assertCount(1, $tenant1Tokens);
        $this->assertEquals('token-1', $tenant1Tokens->first()->name);
    }

    public function test_with_ability_scope(): void
    {
        TenantToken::createToken($this->user, 'read-token', ['read']);
        TenantToken::createToken($this->user, 'write-token', ['write']);
        TenantToken::createToken($this->user, 'admin-token', ['*']);

        $readTokens = TenantToken::withAbility('read')->get();

        $this->assertCount(2, $readTokens);
    }

    public function test_tokenable_relationship(): void
    {
        $result = TenantToken::createToken($this->user, 'test-token');

        $tokenable = $result['token']->tokenable;

        $this->assertInstanceOf(User::class, $tokenable);
        $this->assertEquals($this->user->id, $tokenable->id);
    }

    public function test_tenant_relationship(): void
    {
        $result = TenantToken::createToken($this->user, 'test-token', ['*'], $this->tenant->id);

        $tenant = $result['token']->tenant;

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals($this->tenant->id, $tenant->id);
    }
}
