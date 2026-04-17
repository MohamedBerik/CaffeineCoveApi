<?php
// app/Helpers/helpers.php

if (!function_exists('tenant_cache_key')) {
    /**
     * Generate a tenant-aware cache key.
     *
     * @param string $key
     * @return string
     */
    function tenant_cache_key(string $key): string
    {
        $tenantId = \App\Services\Tenant::id() ?? 'global';
        return "tenant_{$tenantId}_{$key}";
    }
}
