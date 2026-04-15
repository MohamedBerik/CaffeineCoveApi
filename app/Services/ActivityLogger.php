<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Services\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

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
        array $properties = []
    ): ?ActivityLog {
        // ✅ تحديد company_id تلقائيًا لو مش موجود
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            // لو مفيش company_id، مش هنقدر نسجل activity
            return null;
        }

        return ActivityLog::create([
            'company_id'   => $companyId,
            'user_id'      => $user?->id,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'properties'   => $properties,
        ]);
    }

    /**
     * Log a model created event
     */
    public static function logCreated($model, ?Authenticatable $user = null): ?ActivityLog
    {
        return self::log(
            $model->company_id ?? null,
            $user ?? auth()->user(),
            'created',
            get_class($model),
            $model->id,
            ['attributes' => $model->toArray()]
        );
    }

    /**
     * Log a model updated event
     */
    public static function logUpdated($model, array $changes = [], ?Authenticatable $user = null): ?ActivityLog
    {
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
                'changes' => $changes,
                'old' => array_intersect_key($model->getOriginal(), $changes),
            ]
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
            ['attributes' => $model->toArray()]
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
