<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\TreatmentPlanItem;
use App\Services\Erp\AppointmentService;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(protected AppointmentService $appointmentService)
    {
        $this->authorizeResource(Appointment::class, 'appointment', ['except' => ['index', 'show']]);
    }

    public function index(Request $request)
    {
        $paginator = $this->appointmentService->list($request);
        if ($paginator === null) {
            return response()->json(['msg' => 'Doctor profile not found', 'status' => 403], 403);
        }

        return response()->json([
            'msg'    => 'Appointments list',
            'status' => 200,
            'data'   => AppointmentResource::collection($paginator->items()),
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $appointment = $this->appointmentService->show($id);
        $this->authorize('view', $appointment);
        $planItem = TreatmentPlanItem::where('appointment_id', $appointment->id)->first();

        return response()->json([
            'msg'    => 'Appointment details',
            'status' => 200,
            'data'   => (new AppointmentResource($appointment))->additional([
                'treatment_plan_item_id' => $planItem?->id,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $appointment = $this->appointmentService->store($request);
        return response()->json(['msg' => 'Appointment created', 'status' => 201, 'data' => new AppointmentResource($appointment)], 201);
    }

    public function update(Request $request, $id)
    {
        $appointment = $this->appointmentService->update($request, $id);
        $this->authorize('update', $appointment);
        return response()->json(['msg' => 'Appointment updated', 'status' => 200, 'data' => new AppointmentResource($appointment)]);
    }

    public function destroy(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorize('delete', $appointment);
        $this->appointmentService->destroy($request, $id);
        return response()->json(['msg' => 'Appointment deleted', 'status' => 200, 'data' => null]);
    }

    public function book(Request $request)
    {
        return $this->appointmentService->book($request);
    }

    public function cancel(Request $request, $id)
    {
        return $this->appointmentService->cancel($request, $id);
    }

    public function noShow(Request $request, $id)
    {
        return $this->appointmentService->noShow($request, $id);
    }

    public function reschedule(Request $request, $id)
    {
        return $this->appointmentService->reschedule($request, $id);
    }

    public function sendReminder(Request $request, $id)
    {
        return $this->appointmentService->sendReminder($request, $id);
    }
    
    public function complete(Request $request, $id)
    {
        return $this->appointmentService->complete($request, $id);
    }
}
