<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Sepehr_Mohseni\LaraTenAuth\LaraTenAuthServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;
    use InteractsWithAuthentication;
    use MakesHttpRequests;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaraTenAuthServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'TenantAuth' => \Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup package config
        $app['config']->set('laratenauth.tenant_model', \Sepehr_Mohseni\LaraTenAuth\Models\Tenant::class);
        $app['config']->set('laratenauth.token_model', \Sepehr_Mohseni\LaraTenAuth\Models\TenantToken::class);
        $app['config']->set('laratenauth.user_model', \Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures\User::class);
        $app['config']->set('laratenauth.database.tenants_table', 'tenants');
        $app['config']->set('laratenauth.database.tenant_user_table', 'tenant_user');
        $app['config']->set('laratenauth.database.tokens_table', 'tenant_tokens');
        $app['config']->set('laratenauth.database.use_uuid', false);
        $app['config']->set('laratenauth.resolution.strategies', ['subdomain', 'domain', 'header']);
        $app['config']->set('laratenauth.resolution.central_domains', ['localhost', 'example.com']);
        $app['config']->set('laratenauth.resolution.header_name', 'X-Tenant-ID');
        $app['config']->set('laratenauth.resolution.cache_enabled', false);
        $app['config']->set('laratenauth.security.allow_impersonation', true);
    }
}
