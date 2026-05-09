<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class DoctorController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Doctor::class, 'doctor', ['except' => ['index', 'show', 'update']]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $q = Doctor::query()->orderByDesc('id');

        // فلترة حسب الشركة (احتياطي)
        $q->where('company_id', Tenant::id());

        // فلترة حسب الفرع للمستخدمين العاديين (غير المشرفين)
        if (!$user->is_super_admin && $user->branch_id !== null) {
            $q->where('branch_id', $user->branch_id);
        }
        // مدير الشركة (branch_id = null) يرى الجميع

        if ($search = trim((string)$request->get('search', ''))) {
            $q->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'msg' => 'Doctors list',
            'status' => 200,
            'data' => $q->paginate((int)$request->get('per_page', 20)),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Tenant::id();
        $branchId = $request->branch_id ?? Tenant::branchId() ?? $request->user()->branch_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'min:6', 'max:255'],          // ✅ كلمة مرور
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'work_start' => ['nullable', 'date_format:H:i'],
            'work_end' => ['nullable', 'date_format:H:i'],
            'slot_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // ✅ إنشاء المستخدم أولاً
        $user = User::create([
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'name'       => $data['name'],
            'email'      => $data['email'] ?? ($data['name'] . '@temp.com'),
            'password'   => bcrypt($data['password']),
            'role'       => 'doctor',
            'status'     => 1,
            'is_super_admin' => false,
        ]);

        // ✅ إنشاء سجل الطبيب (لأن الحدث في User سيقوم بذلك تلقائياً، لكن قد تحتاج إلى التأكد من عدم التكرار)
        // إذا أردت التحكم الكامل، يمكنك إنشائه هنا:
        $doctor = Doctor::create([
            'user_id'      => $user->id,
            'company_id'   => $companyId,
            'branch_id'    => $branchId,
            'name'         => $data['name'],
            'email'        => $data['email'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'is_active'    => $data['is_active'] ?? true,
            'work_start'   => $data['work_start'] ?? '09:00',
            'work_end'     => $data['work_end'] ?? '21:00',
            'slot_minutes' => $data['slot_minutes'] ?? 30,
            'created_by'   => $request->user()->id ?? null,
        ]);

        return response()->json(['msg' => 'Doctor created', 'status' => 201, 'data' => $doctor], 201);
    }

    public function show(Request $request, $id)
    {
        $doctor = Doctor::query()->findOrFail($id);
        $this->authorize('view', $doctor);

        return response()->json(['msg' => 'Doctor details', 'status' => 200, 'data' => $doctor]);
    }

    public function update(Request $request, $id)
    {
        $doctor = Doctor::query()->findOrFail($id);
        $this->authorize('update', $doctor);

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:190',
                Rule::unique('doctors', 'name')->ignore($doctor->id)
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'work_start' => ['nullable', 'date_format:H:i'],
            'work_end' => ['nullable', 'date_format:H:i'],
            'slot_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $doctor->update($data);

        return response()->json(['msg' => 'Doctor updated', 'status' => 200, 'data' => $doctor->fresh()]);
    }

    public function destroy(Request $request, $id)
    {
        $doctor = Doctor::query()->findOrFail($id);
        $this->authorize('delete', $doctor);

        $hasAppointments = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->exists();

        if ($hasAppointments) {
            return response()->json(['msg' => 'Cannot delete doctor with appointments'], 422);
        }

        $doctor->delete();
        return response()->json(['msg' => 'Doctor deleted', 'status' => 200]);
    }

    public function availability(Request $request, $id)
    {
        $doctor = Doctor::query()->findOrFail($id);

        $date = Carbon::parse($request->query('date', now()->toDateString()))->toDateString();

        $start = Carbon::parse("$date {$doctor->work_start}");
        $end   = Carbon::parse("$date {$doctor->work_end}");

        $slot = max(5, (int)$doctor->slot_minutes);

        $booked = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['scheduled', 'completed', 'no_show'])
            ->pluck('appointment_time')
            ->toArray();

        $slots = [];
        $cur = $start->copy();
        while ($cur->lt($end)) {
            $t = $cur->format('H:i');
            $slots[] = [
                'time' => $t,
                'available' => !in_array($t, $booked, true),
            ];
            $cur->addMinutes($slot);
        }

        return response()->json([
            'msg' => 'Doctor availability',
            'status' => 200,
            'data' => [
                'doctor_id' => $doctor->id,
                'date' => $date,
                'work_start' => $doctor->work_start,
                'work_end' => $doctor->work_end,
                'slot_minutes' => $slot,
                'slots' => $slots,
            ]
        ]);
    }

    // في DoctorController
    public function byUser(Request $request, $userId)
    {
        $doctor = Doctor::where('user_id', $userId)->firstOrFail();
        return response()->json(['data' => $doctor]);
    }
}
