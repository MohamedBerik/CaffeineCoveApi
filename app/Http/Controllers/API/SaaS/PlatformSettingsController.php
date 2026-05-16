<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PlatformSettingsController extends Controller
{
    /**
     * GET /api/admin/settings/platform
     * جلب إعدادات المنصة
     */
    public function index(Request $request)
    {
        // ✅ Super Admin فقط
        if (!auth()->user()->is_super_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settings = $this->getAllSettings();

        return response()->json([
            'msg' => 'Platform settings',
            'status' => 200,
            'data' => $settings,
        ]);
    }

    /**
     * POST /api/admin/settings/platform
     * تحديث إعدادات المنصة
     */
    public function update(Request $request)
    {
        // ✅ Super Admin فقط
        if (!auth()->user()->is_super_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $section = $request->input('section', 'general');
        $data = $request->except(['section']);

        switch ($section) {
            case 'general':
                $this->saveGeneralSettings($data);
                break;
            case 'branding':
                $this->saveBrandingSettings($data);
                break;
            case 'email':
                $this->saveEmailSettings($data);
                break;
            case 'notifications':
                $this->saveNotificationSettings($data);
                break;
            case 'maintenance':
                $this->saveMaintenanceSettings($data);
                break;
            case 'security':
                $this->saveSecuritySettings($data);
                break;
            case 'api':
                $this->saveApiSettings($data);
                break;
        }

        // ✅ مسح الكاش
        Cache::forget('platform_settings');

        return response()->json([
            'msg' => 'Settings saved successfully',
            'status' => 200,
        ]);
    }

    /**
     * جلب كل الإعدادات
     */
    private function getAllSettings(): array
    {
        return Cache::remember('platform_settings', 3600, function () {
            $settings = PlatformSetting::all()->pluck('value', 'key')->toArray();

            return [
                'general' => [
                    'platform_name' => $settings['platform_name'] ?? 'My SaaS Platform',
                    'platform_email' => $settings['platform_email'] ?? 'admin@platform.com',
                    'platform_phone' => $settings['platform_phone'] ?? '',
                    'platform_address' => $settings['platform_address'] ?? '',
                    'default_language' => $settings['default_language'] ?? 'en',
                    'default_timezone' => $settings['default_timezone'] ?? 'Africa/Cairo',
                    'default_currency' => $settings['default_currency'] ?? 'EGP',
                    'date_format' => $settings['date_format'] ?? 'Y-m-d',
                    'time_format' => $settings['time_format'] ?? 'H:i',
                ],
                'branding' => [
                    'logo_url' => $settings['logo_url'] ?? null,
                    'favicon_url' => $settings['favicon_url'] ?? null,
                    'primary_color' => $settings['primary_color'] ?? '#1a237e',
                    'secondary_color' => $settings['secondary_color'] ?? '#283593',
                    'accent_color' => $settings['accent_color'] ?? '#4caf50',
                ],
                'email' => [
                    'mail_mailer' => $settings['mail_mailer'] ?? 'smtp',
                    'mail_host' => $settings['mail_host'] ?? '',
                    'mail_port' => $settings['mail_port'] ?? '587',
                    'mail_username' => $settings['mail_username'] ?? '',
                    'mail_password' => $settings['mail_password'] ?? '',
                    'mail_encryption' => $settings['mail_encryption'] ?? 'tls',
                    'mail_from_address' => $settings['mail_from_address'] ?? '',
                    'mail_from_name' => $settings['mail_from_name'] ?? '',
                ],
                'notifications' => [
                    'email_notifications' => (bool) ($settings['email_notifications'] ?? true),
                    'sms_notifications' => (bool) ($settings['sms_notifications'] ?? false),
                    'push_notifications' => (bool) ($settings['push_notifications'] ?? true),
                    'admin_new_company' => (bool) ($settings['admin_new_company'] ?? true),
                    'admin_new_subscription' => (bool) ($settings['admin_new_subscription'] ?? true),
                    'admin_payment_received' => (bool) ($settings['admin_payment_received'] ?? true),
                    'admin_trial_ending' => (bool) ($settings['admin_trial_ending'] ?? true),
                ],
                'maintenance' => [
                    'maintenance_mode' => (bool) ($settings['maintenance_mode'] ?? false),
                    'maintenance_message' => $settings['maintenance_message'] ?? 'We\'ll be back soon!',
                    'allowed_ips' => $settings['allowed_ips'] ?? '',
                ],
                'security' => [
                    'max_login_attempts' => $settings['max_login_attempts'] ?? '5',
                    'lockout_duration' => $settings['lockout_duration'] ?? '15',
                    'session_lifetime' => $settings['session_lifetime'] ?? '120',
                    'password_expiry_days' => $settings['password_expiry_days'] ?? '90',
                    'two_factor_required' => (bool) ($settings['two_factor_required'] ?? false),
                ],
                'api' => [
                    'api_enabled' => (bool) ($settings['api_enabled'] ?? true),
                    'api_rate_limit' => $settings['api_rate_limit'] ?? '60',
                    'webhook_url' => $settings['webhook_url'] ?? '',
                    'webhook_secret' => $settings['webhook_secret'] ?? '',
                ],
            ];
        });
    }

    private function saveGeneralSettings(array $data): void
    {
        $fields = [
            'platform_name',
            'platform_email',
            'platform_phone',
            'platform_address',
            'default_language',
            'default_timezone',
            'default_currency',
            'date_format',
            'time_format',
        ];
        $this->saveSettings($fields, $data);
    }

    private function saveBrandingSettings(array $data): void
    {
        $fields = ['primary_color', 'secondary_color', 'accent_color'];
        $this->saveSettings($fields, $data);

        // ✅ التعامل مع الملفات المرفوعة (لو فيه)
        if (isset($data['logo']) && $data['logo']) {
            // حفظ اللوجو
            $path = $data['logo']->store('platform', 'public');
            PlatformSetting::updateOrCreate(
                ['key' => 'logo_url'],
                ['value' => asset('storage/' . $path)]
            );
        }

        if (isset($data['favicon']) && $data['favicon']) {
            // حفظ الفافيكون
            $path = $data['favicon']->store('platform', 'public');
            PlatformSetting::updateOrCreate(
                ['key' => 'favicon_url'],
                ['value' => asset('storage/' . $path)]
            );
        }
    }

    private function saveEmailSettings(array $data): void
    {
        $fields = [
            'mail_mailer',
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_from_address',
            'mail_from_name',
        ];
        $this->saveSettings($fields, $data);
    }

    private function saveNotificationSettings(array $data): void
    {
        $fields = [
            'email_notifications',
            'sms_notifications',
            'push_notifications',
            'admin_new_company',
            'admin_new_subscription',
            'admin_payment_received',
            'admin_trial_ending',
        ];
        $this->saveSettings($fields, $data);
    }

    private function saveMaintenanceSettings(array $data): void
    {
        $fields = ['maintenance_mode', 'maintenance_message', 'allowed_ips'];
        $this->saveSettings($fields, $data);
    }

    private function saveSecuritySettings(array $data): void
    {
        $fields = [
            'max_login_attempts',
            'lockout_duration',
            'session_lifetime',
            'password_expiry_days',
            'two_factor_required',
        ];
        $this->saveSettings($fields, $data);
    }

    private function saveApiSettings(array $data): void
    {
        $fields = ['api_enabled', 'api_rate_limit', 'webhook_url', 'webhook_secret'];
        $this->saveSettings($fields, $data);
    }

    private function saveSettings(array $fields, array $data): void
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                PlatformSetting::updateOrCreate(
                    ['key' => $field],
                    ['value' => $data[$field]]
                );
            }
        }
    }

    // app/Http/Controllers/API/SaaS/PlatformSettingsController.php

    /**
     * GET /api/erp/platform-info
     * الحصول على معلومات المنصة العامة (للاستخدام داخل ERP)
     */
    public function platformInfo()
    {
        $general = \App\Models\PlatformSetting::where('key', 'general')
            ->value('value');

        $settings = $general ? json_decode($general, true) : [];

        return response()->json([
            'name' => $settings['platform_name'] ?? config('app.name', 'My Platform'),
            'email' => $settings['platform_email'] ?? '',
            'phone' => $settings['platform_phone'] ?? '',
            'address' => $settings['platform_address'] ?? '',
        ]);
    }
}
