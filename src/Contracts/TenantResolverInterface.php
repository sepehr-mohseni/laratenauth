<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface TenantResolverInterface
{
    /**
     * Resolve tenant from the given request.
     */
    public function resolve(Request $request): ?Model;

    /**
     * Resolve tenant by identifier.
     */
    public function resolveById(int|string $identifier): ?Model;

    /**
     * Register a custom resolution strategy.
     */
    public function registerStrategy(string $name, callable $callback): void;

    /**
     * Clear tenant resolution cache.
     */
    public function clearCache(): void;
}
