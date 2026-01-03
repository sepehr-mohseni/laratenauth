<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Sepehr_Mohseni\LaraTenAuth\Exceptions\InvalidTenantTokenException;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantAccessDeniedException;
use Sepehr_Mohseni\LaraTenAuth\Exceptions\TenantNotResolvedException;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class ExceptionsTest extends TestCase
{
    public function test_invalid_tenant_token_exception_make(): void
    {
        $exception = InvalidTenantTokenException::make();
        
        $this->assertInstanceOf(InvalidTenantTokenException::class, $exception);
        $this->assertStringContainsString('invalid', strtolower($exception->getMessage()));
    }

    public function test_invalid_tenant_token_exception_make_with_custom_message(): void
    {
        $exception = InvalidTenantTokenException::make('Custom message');
        
        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function test_invalid_tenant_token_exception_wrong_tenant(): void
    {
        $exception = InvalidTenantTokenException::wrongTenant();
        
        $this->assertInstanceOf(InvalidTenantTokenException::class, $exception);
        $this->assertStringContainsString('tenant', strtolower($exception->getMessage()));
    }

    public function test_invalid_tenant_token_exception_expired(): void
    {
        $exception = InvalidTenantTokenException::expired();
        
        $this->assertInstanceOf(InvalidTenantTokenException::class, $exception);
        $this->assertStringContainsString('expired', strtolower($exception->getMessage()));
    }

    public function test_invalid_tenant_token_exception_revoked(): void
    {
        $exception = InvalidTenantTokenException::revoked();
        
        $this->assertInstanceOf(InvalidTenantTokenException::class, $exception);
        $this->assertStringContainsString('revoked', strtolower($exception->getMessage()));
    }

    public function test_tenant_access_denied_exception_make(): void
    {
        $exception = TenantAccessDeniedException::make();
        
        $this->assertInstanceOf(TenantAccessDeniedException::class, $exception);
        $this->assertStringContainsString('access', strtolower($exception->getMessage()));
    }

    public function test_tenant_access_denied_exception_make_with_custom_message(): void
    {
        $exception = TenantAccessDeniedException::make('Custom access denied');
        
        $this->assertEquals('Custom access denied', $exception->getMessage());
    }

    public function test_tenant_access_denied_exception_for_user(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        
        $exception = TenantAccessDeniedException::forUser($user, $tenant);
        
        $this->assertInstanceOf(TenantAccessDeniedException::class, $exception);
        $this->assertStringContainsString((string) $user->id, $exception->getMessage());
        $this->assertStringContainsString((string) $tenant->id, $exception->getMessage());
    }

    public function test_tenant_not_resolved_exception_make(): void
    {
        $exception = TenantNotResolvedException::make();
        
        $this->assertInstanceOf(TenantNotResolvedException::class, $exception);
        $this->assertStringContainsString('tenant', strtolower($exception->getMessage()));
    }

    public function test_tenant_not_resolved_exception_cannot_access_with_tenant(): void
    {
        $exception = TenantNotResolvedException::cannotAccessWithTenant();
        
        $this->assertInstanceOf(TenantNotResolvedException::class, $exception);
        $this->assertStringContainsString('cannot', strtolower($exception->getMessage()));
    }
}
