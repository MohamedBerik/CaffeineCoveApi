<?php

namespace App\Services;

class AlertTemplateService
{
    public static function render(
        string $code,
        array $data = [],
        ?string $locale = null
    ): string {
        $locale ??= app()->getLocale();

        $key = 'alerts.' . $code;

        $message = trans($key, $data, $locale);

        if ($message === $key) {
            return trans(
                'alerts.default',
                [],
                $locale
            );
        }

        return $message;
    }
}
