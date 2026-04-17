<?php

if (!function_exists('tenant_cache_key')) {
    function tenant_cache_key($key)
    {
        return 'tenant_' . (\App\Services\Tenant::id() ?? 'global') . '_' . $key;
    }
}
