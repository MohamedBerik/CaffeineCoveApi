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

        // ✅ المصادر الصحيحة للـ branch_id (بدون الاعتماد على X-Branch-Id)
        $branchId = $branchId
            ?? $user?->branch_id
            ?? Tenant::branchId()
            ?? null;

        // ✅ إضافة meta data تلقائيًا
        $properties['meta'] = [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'branch_id' => $branchId,
        ];

        $log = ActivityLog::create([
            'company_id'   => $companyId,
            'branch_id'    => $branchId,
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

    public static function logCreated($model, ?Authenticatable $user = null): ?ActivityLog
    {
        return self::log(
            $model->company_id ?? null,
            $user ?? auth()->user(),
            class_basename($model) . '.created',
            get_class($model),
            $model->id,
            ['attributes' => $model->toArray()],
            $model->branch_id ?? null
        );
    }

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
            $model->branch_id ?? null
        );
    }

    public static function logDeleted($model, ?Authenticatable $user = null): ?ActivityLog
    {
        return self::log(
            $model->company_id ?? null,
            $user ?? auth()->user(),
            'deleted',
            get_class($model),
            $model->id,
            ['attributes' => $model->toArray()],
            $model->branch_id ?? null
        );
    }

    public static function logAction(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = [],
        ?Authenticatable $user = null
    ): ?ActivityLog {
        $currentUser = $user ?? auth()->user();

        return self::log(
            Tenant::id(),
            $currentUser,
            $action,
            $subjectType ?? 'system',
            $subjectId,
            $properties,
            $currentUser?->branch_id   // ✅ تمرير الفرع
        );
    }

    public static function logSecurity(
        string $action,
        array $properties = [],
        ?Authenticatable $user = null
    ): ?ActivityLog {
        $currentUser = $user ?? auth()->user();

        return self::log(
            Tenant::id(),
            $currentUser,
            'security.' . $action,
            'security',
            null,
            $properties,
            $currentUser?->branch_id   // ✅
        );
    }

    public static function logError(
        string $message,
        array $context = [],
        ?Authenticatable $user = null
    ): ?ActivityLog {
        $currentUser = $user ?? auth()->user();

        return self::log(
            Tenant::id(),
            $currentUser,
            'error',
            'system',
            null,
            ['message' => $message, 'context' => $context],
            $currentUser?->branch_id   // ✅
        );
    }

    public static function logLogin(Authenticatable $user, bool $success = true, array $meta = []): ?ActivityLog
    {
        return self::log(
            $user->company_id ?? null,
            $user,
            $success ? 'login.success' : 'login.failed',
            get_class($user),
            $user->id,
            array_merge(['ip' => request()->ip(), 'user_agent' => request()->userAgent()], $meta)
        );
    }

    public static function logLogout(Authenticatable $user): ?ActivityLog
    {
        return self::log(
            $user->company_id ?? null,
            $user,
            'logout',
            get_class($user),
            $user->id,
            ['ip' => request()->ip()]
        );
    }
}
