<?php

if (!function_exists('format_currency')) {
    function format_currency($amount, string $currency = 'EGP'): string
    {
        return number_format((float) $amount, 2) . ' ' . $currency;
    }
}

if (!function_exists('format_date')) {
    function format_date($date, string $format = 'Y-m-d'): string
    {
        if (!$date) return '';
        return \Carbon\Carbon::parse($date)->format($format);
    }
}

if (!function_exists('format_time')) {
    function format_time($time, string $format = 'H:i'): string
    {
        if (!$time) return '';
        return \Carbon\Carbon::parse($time)->format($format);
    }
}
