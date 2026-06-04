<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Services\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Events\ActivityLogCreated;

class ActivityLogger
{
    /**
     * Log an activity
     *
     * @param int|null $companyId
     * @param Authenticatable|null $user
     * @param string $action
     * @param string $subjectType
     * @param int|null $subjectId
     * @param array $properties
     * @param int|null $branchId ✅ أضفناه لدعم الفروع
     */
    public static function log(
        ?int $companyId,
        ?Authenticatable $user,
        string $action,
        string $subjectType,
        ?int $subjectId = null,
        array $properties = [],
        ?int $branchId = null
    ): ?ActivityLog {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            return null;
        }

        // ✅ محاولة جلب branch_id من الطلب إذا لم يمرر صراحةً
        if ($branchId === null) {
            $branchId = request()->header('X-Branch-Id') ?? null;
        }

        // ✅ إضافة meta data تلقائيًا
        $properties['meta'] = [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'branch_id' => $branchId, // ✅ تسجيل الفرع في meta
        ];

        $log = ActivityLog::create([
            'company_id'   => $companyId,
            'branch_id'    => $branchId, // ✅ إدراج الفرع
            'user_id'      => $user?->id,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'properties'   => $properties,
        ]);

        $log->loadMissing('user');

        event(new ActivityLogCreated($log));

        return $log;
    }

    /**
     * Log a model created event
     */
    public static function logCreated($model, ?Authenticatable $user = null): ?ActivityLog
    {
        return self::log(
            $model->company_id ?? null,
            $user ?? auth()->user(),
            class_basename($model) . '.created',
            get_class($model),
            $model->id,
            ['attributes' => $model->toArray()],
            $model->branch_id ?? null // ✅ يمرر branch_id من النموذج
        );
    }

    /**
     * Log a model updated event
     */
    public static function logUpdated($model, array $changes = [], ?Authenticatable $user = null): ?ActivityLog
    {
        $changedFields = array_keys($changes);

        if (empty($changes)) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
        }

        if (empty($changes)) {
            return null;
        }

        return self::log(
            $model->company_id ?? null,
            $user ?? auth()->user(),
            'updated',
            get_class($model),
            $model->id,
            [
                'old' => array_intersect_key($model->getOriginal(), $changes),
                'new' => $changes,
                'changed_fields' => $changedFields,
            ],
            $model->branch_id ?? null // ✅ فرع النموذج
        );
    }

    /**
     * Log a model deleted event
     */
    public static function logDeleted($model, ?Authenticatable $user = null): ?ActivityLog
    {
        return self::log(
            $model->company_id ?? null,
            $user ?? auth()->user(),
            'deleted',
            get_class($model),
            $model->id,
            ['attributes' => $model->toArray()],
            $model->branch_id ?? null // ✅ فرع النموذج
        );
    }

    /**
     * Log a custom action
     */
    public static function logAction(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = [],
        ?Authenticatable $user = null
    ): ?ActivityLog {
        return self::log(
            Tenant::id(),
            $user ?? auth()->user(),
            $action,
            $subjectType ?? 'system',
            $subjectId,
            $properties
        );
    }

    /**
     * Log a security event
     */
    public static function logSecurity(
        string $action,
        array $properties = [],
        ?Authenticatable $user = null
    ): ?ActivityLog {
        return self::log(
            Tenant::id(),
            $user ?? auth()->user(),
            'security.' . $action,
            'security',
            null,
            $properties
        );
    }

    /**
     * Log an error event
     */
    public static function logError(
        string $message,
        array $context = [],
        ?Authenticatable $user = null
    ): ?ActivityLog {
        return self::log(
            Tenant::id(),
            $user ?? auth()->user(),
            'error',
            'system',
            null,
            [
                'message' => $message,
                'context' => $context,
            ]
        );
    }

    /**
     * Log a login event
     */
    public static function logLogin(Authenticatable $user, bool $success = true, array $meta = []): ?ActivityLog
    {
        return self::log(
            $user->company_id ?? null,
            $user,
            $success ? 'login.success' : 'login.failed',
            get_class($user),
            $user->id,
            array_merge([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ], $meta)
        );
    }

    /**
     * Log a logout event
     */
    public static function logLogout(Authenticatable $user): ?ActivityLog
    {
        return self::log(
            $user->company_id ?? null,
            $user,
            'logout',
            get_class($user),
            $user->id,
            [
                'ip' => request()->ip(),
            ]
        );
    }
}
