<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class AdminCrudService
{
    /**
     * Get allowed tables (cached)
     */
    public function getAllowedTables(): array
    {
        if (!$this->isCacheEnabled()) {
            return Config::get('admin-crud.allowed_tables', []);
        }

        return Cache::remember(
            $this->getCacheKey('allowed_tables'),
            $this->getCacheTTL(),
            fn() => Config::get('admin-crud.allowed_tables', [])
        );
    }

    /**
     * Get table permissions (cached)
     */
    public function getTablePermissions(string $table): array
    {
        if (!$this->isCacheEnabled()) {
            $permissions = Config::get('admin-crud.permissions', []);
            return $permissions[$table] ?? ['view'];
        }

        $cacheKey = $this->getCacheKey("permissions_{$table}");

        return Cache::remember(
            $cacheKey,
            $this->getCacheTTL(),
            function () use ($table) {
                $permissions = Config::get('admin-crud.permissions', []);
                return $permissions[$table] ?? ['view'];
            }
        );
    }

    /**
     * Get sensitive columns (cached)
     */
    public function getSensitiveColumns(): array
    {
        if (!$this->isCacheEnabled()) {
            return Config::get('admin-crud.sensitive_columns', []);
        }

        return Cache::remember(
            $this->getCacheKey('sensitive_columns'),
            $this->getCacheTTL(),
            fn() => Config::get('admin-crud.sensitive_columns', [])
        );
    }

    /**
     * Get safe columns for a table (cached)
     */
    public function getSafeColumns(string $table): array
    {
        if (!$this->isCacheEnabled()) {
            return $this->computeSafeColumns($table);
        }

        $cacheKey = $this->getCacheKey("columns_{$table}");

        return Cache::remember(
            $cacheKey,
            $this->getCacheTTL(),
            fn() => $this->computeSafeColumns($table)
        );
    }

    /**
     * Compute safe columns (without cache)
     */
    private function computeSafeColumns(string $table): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $allColumns = Schema::getColumnListing($table);
        $sensitiveColumns = $this->getSensitiveColumns();

        return array_values(array_diff($allColumns, $sensitiveColumns));
    }

    /**
     * Check if table has company_id column (cached)
     */
    public function tableHasCompanyId(string $table): bool
    {
        if (!$this->isCacheEnabled()) {
            return Schema::hasColumn($table, 'company_id');
        }

        $cacheKey = $this->getCacheKey("has_company_id_{$table}");

        return Cache::remember(
            $cacheKey,
            $this->getCacheTTL(),
            fn() => Schema::hasColumn($table, 'company_id')
        );
    }

    /**
     * Clear cache for a specific table
     */
    public function clearTableCache(string $table): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }

        Cache::forget($this->getCacheKey("columns_{$table}"));
        Cache::forget($this->getCacheKey("has_company_id_{$table}"));
        Cache::forget($this->getCacheKey("permissions_{$table}"));
    }

    /**
     * Clear all admin-crud cache
     */
    public function clearAllCache(): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }

        $prefix = Config::get('admin-crud.cache.prefix', 'admin_crud_');

        // Get all cache keys with prefix
        $keys = Cache::getStore()->many(Cache::getStore()->getPrefix() . $prefix . '*');

        foreach (array_keys($keys) as $key) {
            Cache::forget(str_replace(Cache::getStore()->getPrefix(), '', $key));
        }
    }

    /**
     * Check if cache is enabled
     */
    private function isCacheEnabled(): bool
    {
        return Config::get('admin-crud.cache.enabled', true);
    }

    /**
     * Get cache TTL
     */
    private function getCacheTTL(): int
    {
        return Config::get('admin-crud.cache.ttl', 3600);
    }

    /**
     * Get cache key with prefix
     */
    private function getCacheKey(string $key): string
    {
        $prefix = Config::get('admin-crud.cache.prefix', 'admin_crud_');
        return $prefix . $key;
    }
}
