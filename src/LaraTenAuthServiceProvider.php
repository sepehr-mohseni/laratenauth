<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Sepehr_Mohseni\LaraTenAuth\Contracts\TenantResolverInterface;
use Sepehr_Mohseni\LaraTenAuth\Guards\TenantSessionGuard;
use Sepehr_Mohseni\LaraTenAuth\Guards\TenantTokenGuard;
use Sepehr_Mohseni\LaraTenAuth\Models\TenantToken;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantAuthManager;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantContextManager;
use Sepehr_Mohseni\LaraTenAuth\Services\TenantResolver;

class LaraTenAuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laratenauth.php',
            'laratenauth'
        );

        $this->registerServices();
        $this->registerGuards();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishConfiguration();
        $this->publishMigrations();
        $this->registerMiddleware();
        $this->registerCommands();
    }

    /**
     * Register package services.
     */
    protected function registerServices(): void
    {
        $this->app->singleton(TenantResolverInterface::class, function (Application $app) {
            return new TenantResolver(
                $app['config'],
                $app['cache.store'],
                $app['request']
            );
        });

        $this->app->singleton('tenant.context', function (Application $app) {
            return new TenantContextManager(
                $app[TenantResolverInterface::class],
                $app['events']
            );
        });

        $this->app->singleton('tenant.auth', function (Application $app) {
            return new TenantAuthManager(
                $app,
                $app['tenant.context'],
                $app['config']
            );
        });

        // Alias for TenantAuth facade
        $this->app->singleton('laratenauth', function (Application $app) {
            return $app['tenant.auth'];
        });
        $this->app->alias('tenant.auth', TenantAuthManager::class);
        $this->app->alias('tenant.context', TenantContextManager::class);
    }

    /**
     * Register custom authentication guards.
     */
    protected function registerGuards(): void
    {
        $this->app['auth']->extend('tenant-session', function (Application $app, string $name, array $config) {
            $provider = $app['auth']->createUserProvider($config['provider'] ?? null);

            $guard = new TenantSessionGuard(
                $name,
                $provider,
                $app['session.store'],
                $app['request']
            );

            $guard->setTenantContext($app['tenant.context']);
            $guard->setCrossTenantAuth($app['config']->get('laratenauth.cross_tenant_auth', false));

            return $guard;
        });

        $this->app['auth']->extend('tenant-token', function (Application $app, string $name, array $config) {
            $provider = $app['auth']->createUserProvider($config['provider'] ?? null);

            $guard = new TenantTokenGuard(
                $name,
                $provider,
                $app['request'],
                $config['token_model'] ?? TenantToken::class
            );

            $guard->setTenantContext($app['tenant.context']);
            $guard->setCrossTenantAuth($app['config']->get('laratenauth.cross_tenant_auth', false));

            return $guard;
        });
    }

    /**
     * Publish configuration file.
     */
    protected function publishConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laratenauth.php' => config_path('laratenauth.php'),
            ], 'laratenauth-config');
        }
    }

    /**
     * Publish migration files.
     */
    protected function publishMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $timestamp = date('Y_m_d_His');

            $this->publishes([
                __DIR__.'/../database/migrations/create_tenants_table.php.stub' =>
                    database_path("migrations/{$timestamp}_create_tenants_table.php"),
                __DIR__.'/../database/migrations/create_tenant_users_table.php.stub' =>
                    database_path("migrations/{$timestamp}_create_tenant_users_table.php"),
                __DIR__.'/../database/migrations/create_tenant_tokens_table.php.stub' =>
                    database_path("migrations/{$timestamp}_create_tenant_tokens_table.php"),
            ], 'laratenauth-migrations');
        }
    }

    /**
     * Register package middleware.
     */
    protected function registerMiddleware(): void
    {
        if (! $this->app['config']->get('laratenauth.middleware.auto_register', true)) {
            return;
        }

        $router = $this->app->make(Router::class);
        $aliases = $this->app['config']->get('laratenauth.middleware.aliases', []);

        foreach ($aliases as $alias => $middleware) {
            $router->aliasMiddleware($alias, $middleware);
        }
    }

    /**
     * Register package commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Add console commands here
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'tenant.context',
            'tenant.auth',
            TenantResolverInterface::class,
            TenantContextManager::class,
            TenantAuthManager::class,
        ];
    }
}
