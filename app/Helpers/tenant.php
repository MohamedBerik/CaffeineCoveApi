<?php

if (!function_exists('tenant_cache_key')) {
    function tenant_cache_key(string $key): string
    {
        $tenantId = \App\Services\Tenant::id() ?? 'global';
        return "tenant_{$tenantId}_{$key}";
    }
}
