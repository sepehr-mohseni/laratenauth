<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Sepehr_Mohseni\LaraTenAuth\Contracts\TenantResolverInterface;

class TenantResolver implements TenantResolverInterface
{
    /**
     * Custom resolution strategies.
     *
     * @var array<string, callable>
     */
    protected array $customStrategies = [];

    /**
     * Create a new tenant resolver instance.
     */
    public function __construct(
        protected ConfigRepository $config,
        protected CacheRepository $cache,
        protected ?Request $request = null
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(Request $request): ?Model
    {
        $this->request = $request;

        $strategies = $this->config->get('laratenauth.resolution.strategies', []);

        foreach ($strategies as $strategy) {
            $tenant = $this->resolveUsingStrategy($strategy, $request);

            if ($tenant !== null) {
                return $this->cacheTenant($tenant);
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function resolveById(int|string $identifier): ?Model
    {
        $cacheKey = $this->getCacheKey("id:{$identifier}");

        if ($this->isCacheEnabled()) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $tenantModel = $this->getTenantModel();
        $tenant = $tenantModel::where('id', $identifier)
            ->where('is_active', true)
            ->first();

        if ($tenant !== null && $this->isCacheEnabled()) {
            $this->cache->put($cacheKey, $tenant, $this->getCacheTtl());
        }

        return $tenant;
    }

    /**
     * {@inheritDoc}
     */
    public function registerStrategy(string $name, callable $callback): void
    {
        $this->customStrategies[$name] = $callback;
    }

    /**
     * {@inheritDoc}
     */
    public function clearCache(): void
    {
        $this->cache->forget($this->getCacheKey('*'));
    }

    /**
     * Resolve tenant using a specific strategy.
     */
    protected function resolveUsingStrategy(string $strategy, Request $request): ?Model
    {
        return match ($strategy) {
            'subdomain' => $this->resolveBySubdomain($request),
            'domain' => $this->resolveByDomain($request),
            'path' => $this->resolveByPath($request),
            'header' => $this->resolveByHeader($request),
            default => $this->resolveByCustomStrategy($strategy, $request),
        };
    }

    /**
     * Resolve tenant by subdomain.
     */
    protected function resolveBySubdomain(Request $request): ?Model
    {
        $host = $request->getHost();
        $centralDomains = $this->config->get('laratenauth.resolution.central_domains', []);

        foreach ($centralDomains as $centralDomain) {
            if (Str::endsWith($host, $centralDomain)) {
                $subdomain = Str::before($host, '.'.$centralDomain);

                if ($subdomain && $subdomain !== $centralDomain) {
                    return $this->findTenantBy('subdomain', $subdomain);
                }
            }
        }

        return null;
    }

    /**
     * Resolve tenant by domain.
     */
    protected function resolveByDomain(Request $request): ?Model
    {
        $host = $request->getHost();

        return $this->findTenantBy('domain', $host);
    }

    /**
     * Resolve tenant by path parameter.
     */
    protected function resolveByPath(Request $request): ?Model
    {
        $parameterName = $this->config->get('laratenauth.resolution.path_parameter', 'tenant');
        $tenantSlug = $request->route($parameterName);

        if ($tenantSlug) {
            return $this->findTenantBy('slug', $tenantSlug);
        }

        return null;
    }

    /**
     * Resolve tenant by header.
     */
    protected function resolveByHeader(Request $request): ?Model
    {
        $headerName = $this->config->get('laratenauth.resolution.header_name', 'X-Tenant-ID');
        $tenantId = $request->header($headerName);

        if ($tenantId) {
            return $this->findTenantBy('id', $tenantId);
        }

        return null;
    }

    /**
     * Resolve tenant using a custom strategy.
     */
    protected function resolveByCustomStrategy(string $strategy, Request $request): ?Model
    {
        if (isset($this->customStrategies[$strategy])) {
            return call_user_func($this->customStrategies[$strategy], $request, $this);
        }

        return null;
    }

    /**
     * Find tenant by a specific column.
     */
    protected function findTenantBy(string $column, mixed $value): ?Model
    {
        $cacheKey = $this->getCacheKey("{$column}:{$value}");

        if ($this->isCacheEnabled()) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $tenantModel = $this->getTenantModel();
        $tenant = $tenantModel::where($column, $value)
            ->where('is_active', true)
            ->first();

        if ($tenant !== null && $this->isCacheEnabled()) {
            $this->cache->put($cacheKey, $tenant, $this->getCacheTtl());
        }

        return $tenant;
    }

    /**
     * Cache the resolved tenant.
     */
    protected function cacheTenant(Model $tenant): Model
    {
        if ($this->isCacheEnabled()) {
            $this->cache->put($this->getCacheKey('current'), $tenant, $this->getCacheTtl());
        }

        return $tenant;
    }

    /**
     * Get cache key for tenant resolution.
     */
    protected function getCacheKey(string $suffix): string
    {
        return "laratenauth:tenant:{$suffix}";
    }

    /**
     * Check if caching is enabled.
     */
    protected function isCacheEnabled(): bool
    {
        return $this->config->get('laratenauth.resolution.cache_enabled', true);
    }

    /**
     * Get cache TTL.
     */
    protected function getCacheTtl(): int
    {
        return $this->config->get('laratenauth.resolution.cache_ttl', 3600);
    }

    /**
     * Get the tenant model class.
     */
    protected function getTenantModel(): string
    {
        return $this->config->get('laratenauth.tenant_model');
    }
}
