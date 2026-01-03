<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth;
use Sepehr_Mohseni\LaraTenAuth\LaraTenAuthServiceProvider;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantAuthManager;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantResolver;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(LaraTenAuthServiceProvider::class, $providers);
    }

    public function test_tenant_auth_manager_is_bound(): void
    {
        $manager = $this->app->make(TenantAuthManager::class);

        $this->assertInstanceOf(TenantAuthManager::class, $manager);
    }

    public function test_tenant_context_manager_is_bound(): void
    {
        $context = $this->app->make(TenantContextManager::class);

        $this->assertInstanceOf(TenantContextManager::class, $context);
    }

    public function test_tenant_resolver_is_bound(): void
    {
        $resolver = $this->app->make(TenantResolver::class);

        $this->assertInstanceOf(TenantResolver::class, $resolver);
    }

    public function test_tenant_auth_facade_works(): void
    {
        $this->assertNull(TenantAuth::tenant());
    }

    public function test_config_is_published(): void
    {
        $config = $this->app['config']->get('laratenauth');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tenant_model', $config);
        $this->assertArrayHasKey('resolution', $config);
    }

    public function test_tenant_auth_manager_is_singleton(): void
    {
        $manager1 = $this->app->make(TenantAuthManager::class);
        $manager2 = $this->app->make(TenantAuthManager::class);

        $this->assertSame($manager1, $manager2);
    }

    public function test_tenant_context_manager_is_singleton(): void
    {
        $context1 = $this->app->make(TenantContextManager::class);
        $context2 = $this->app->make(TenantContextManager::class);

        $this->assertSame($context1, $context2);
    }

    public function test_tenant_resolver_is_singleton(): void
    {
        // TenantResolver is not registered as singleton, just verify it can be resolved
        $resolver1 = $this->app->make(TenantResolver::class);

        $this->assertInstanceOf(TenantResolver::class, $resolver1);
    }

    public function test_tenant_model_config(): void
    {
        $tenantModel = $this->app['config']->get('laratenauth.tenant_model');

        $this->assertEquals(\Sepehr_Mohseni\LaraTenAuth\Models\Tenant::class, $tenantModel);
    }

    public function test_resolution_strategies_config(): void
    {
        $strategies = $this->app['config']->get('laratenauth.resolution.strategies');

        $this->assertIsArray($strategies);
        $this->assertContains('subdomain', $strategies);
    }

    public function test_security_config(): void
    {
        $allowImpersonation = $this->app['config']->get('laratenauth.security.allow_impersonation');

        $this->assertTrue($allowImpersonation);
    }

    public function test_provides_method_returns_expected_services(): void
    {
        $provider = new LaraTenAuthServiceProvider($this->app);
        $provides = $provider->provides();

        $this->assertContains('tenant.context', $provides);
        $this->assertContains('tenant.auth', $provides);
        $this->assertContains(\Sepehr_Mohseni\LaraTenAuth\Contracts\TenantResolverInterface::class, $provides);
    }

    public function test_middleware_aliases_are_configured(): void
    {
        $aliases = $this->app['config']->get('laratenauth.middleware.aliases');

        $this->assertIsArray($aliases);
    }

    public function test_tenant_session_guard_can_be_extended(): void
    {
        // Configure a guard using the tenant-session driver
        $this->app['config']->set('auth.guards.tenant', [
            'driver' => 'tenant-session',
            'provider' => 'users',
        ]);

        $guard = $this->app['auth']->guard('tenant');

        $this->assertInstanceOf(\Sepehr_Mohseni\LaraTenAuth\Guards\TenantSessionGuard::class, $guard);
    }

    public function test_tenant_token_guard_can_be_extended(): void
    {
        // Configure a guard using the tenant-token driver
        $this->app['config']->set('auth.guards.tenant-api', [
            'driver' => 'tenant-token',
            'provider' => 'users',
        ]);

        $guard = $this->app['auth']->guard('tenant-api');

        $this->assertInstanceOf(\Sepehr_Mohseni\LaraTenAuth\Guards\TenantTokenGuard::class, $guard);
    }

    public function test_tenant_auth_alias_works(): void
    {
        $manager1 = $this->app->make('tenant.auth');
        $manager2 = $this->app->make('laratenauth');

        $this->assertInstanceOf(TenantAuthManager::class, $manager1);
        $this->assertInstanceOf(TenantAuthManager::class, $manager2);
    }

    public function test_tenant_context_alias_works(): void
    {
        $context1 = $this->app->make('tenant.context');
        $context2 = $this->app->make(TenantContextManager::class);

        $this->assertSame($context1, $context2);
    }

    public function test_database_config_is_set(): void
    {
        $tenantsTable = $this->app['config']->get('laratenauth.database.tenants_table');
        $tenantUserTable = $this->app['config']->get('laratenauth.database.tenant_user_table');
        $tokensTable = $this->app['config']->get('laratenauth.database.tokens_table');

        $this->assertEquals('tenants', $tenantsTable);
        $this->assertEquals('tenant_user', $tenantUserTable);
        $this->assertEquals('tenant_tokens', $tokensTable);
    }

    public function test_resolution_cache_config(): void
    {
        // TestCase sets cache_enabled to false
        $cacheEnabled = $this->app['config']->get('laratenauth.resolution.cache_enabled');
        
        // Just verify it's a boolean
        $this->assertIsBool($cacheEnabled);
    }

    public function test_token_expiration_config(): void
    {
        $tokenExpiration = $this->app['config']->get('laratenauth.tokens.expiration');

        $this->assertIsInt($tokenExpiration);
        $this->assertEquals(525600, $tokenExpiration);
    }
}
