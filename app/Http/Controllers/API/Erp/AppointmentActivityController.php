<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Services\Tenant;
use Illuminate\Http\Request;

class AppointmentActivityController extends Controller
{
    public function index(Request $request, $id)
    {
        // 1. جلب الموعد مع التأكيد على تبعيته لنفس الشركة
        $appointment = Appointment::where('company_id', Tenant::id())->findOrFail($id);

        // 2. التحقق من الصلاحية (سيأخذ بالاعتبار الفرع والدور من خلال Policy)
        $this->authorize('view', $appointment);

        // 3. بناء الاستعلام على سجلات النشاط
        $logsQuery = ActivityLog::with('user:id,name')
            ->where('subject_type', Appointment::class)
            ->where('subject_id', $appointment->id);

        // فلترة حسب company_id إن كان العمود موجوداً (احتياطي)
        if (app(ActivityLog::class)->getConnection()->getSchemaBuilder()->hasColumn('activity_logs', 'company_id')) {
            $logsQuery->where('company_id', Tenant::id());
        }

        // 4. ترتيب تنازلي مع pagination للحفاظ على الأداء
        $logs = $logsQuery->orderByDesc('id')
            ->paginate((int) $request->get('per_page', 20));

        // 5. تنسيق البيانات (حافظت على نفس الحقول السابقة)
        $data = $logs->through(fn($log) => [
            'id' => $log->id,
            'action' => $log->action,
            'user_id' => $log->user_id,
            'user_name' => $log->user?->name,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'properties' => $log->properties,
            'created_at' => $log->created_at?->toISOString(),
        ]);

        return response()->json([
            'msg' => 'Appointment activity',
            'status' => 200,
            'data' => $data,
        ]);
    }
}
