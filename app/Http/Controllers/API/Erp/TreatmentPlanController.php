<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\TreatmentPlanResource;
use App\Models\TreatmentPlan;
use App\Services\Erp\TreatmentPlanService;
use Illuminate\Http\Request;

class TreatmentPlanController extends Controller
{
    public function __construct(
        protected TreatmentPlanService $treatmentPlanService
    ) {
        $this->authorizeResource(TreatmentPlan::class, 'treatmentPlan', [
            'except' => ['index', 'show', 'startItem', 'attachAppointment', 'store']
        ]);
    }

    public function index(Request $request)
    {
        $paginator = $this->treatmentPlanService->list($request);
        return response()->json($paginator);
    }

    public function show(Request $request, $id)
    {
        $plan = TreatmentPlan::findOrFail($id);
        $this->authorize('view', $plan);
        $data = $this->treatmentPlanService->show($request, $id);
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $data = $this->treatmentPlanService->store($request);
        return response()->json(['msg' => 'Treatment plan created', 'data' => $data], 201);
    }

    public function update(Request $request, $id)
    {
        $plan = TreatmentPlan::findOrFail($id);
        $this->authorize('update', $plan);
        $data = $this->treatmentPlanService->update($request, $id);
        return response()->json(['msg' => 'Treatment plan updated', 'data' => $data]);
    }

    public function destroy(Request $request, $id)
    {
        $plan = TreatmentPlan::findOrFail($id);
        $this->authorize('delete', $plan);
        $result = $this->treatmentPlanService->destroy($request, $id);
        if ($result instanceof \Illuminate\Http\JsonResponse) return $result;
        return response()->json(['msg' => 'Treatment plan deleted']);
    }

    public function summary(Request $request, $id)
    {
        $data = $this->treatmentPlanService->summary($request, $id);
        return response()->json(['msg' => 'Treatment plan summary', 'status' => 200, 'data' => $data]);
    }

    public function cashSummary(Request $request, $id)
    {
        $data = $this->treatmentPlanService->cashSummary($request, $id);
        return response()->json(['msg' => 'Treatment plan cash summary', 'status' => 200, 'data' => $data]);
    }

    public function items(Request $request, $planId)
    {
        $data = $this->treatmentPlanService->items($request, $planId);
        return response()->json(['msg' => 'Treatment plan items', 'status' => 200, 'data' => $data]);
    }

    public function addItem(Request $request, $planId)
    {
        $data = $this->treatmentPlanService->addItem($request, $planId);
        return response()->json(['msg' => 'Item added', 'status' => 201, 'data' => $data], 201);
    }

    public function updateItem(Request $request, $itemId)
    {
        $result = $this->treatmentPlanService->updateItem($request, $itemId);
        if ($result instanceof \Illuminate\Http\JsonResponse) return $result;
        return response()->json(['msg' => 'Item updated', 'status' => 200, 'data' => $result]);
    }

    public function deleteItem(Request $request, $itemId)
    {
        $result = $this->treatmentPlanService->deleteItem($request, $itemId);
        if ($result instanceof \Illuminate\Http\JsonResponse) return $result;
        return response()->json(['msg' => 'Item deleted', 'status' => 200]);
    }

    public function startItem(Request $request, $itemId)
    {
        $planItem = \App\Models\TreatmentPlanItem::findOrFail($itemId);
        $plan = TreatmentPlan::findOrFail($planItem->treatment_plan_id);
        $this->authorize('startItem', $plan);

        $result = $this->treatmentPlanService->startItem($request, $itemId);
        if ($result instanceof \Illuminate\Http\JsonResponse) return $result;
        return response()->json(['msg' => 'Procedure started successfully', 'status' => 201, 'data' => $result], 201);
    }

    public function attachAppointment(Request $request, $itemId)
    {
        $item = \App\Models\TreatmentPlanItem::with('plan')->findOrFail($itemId);
        $this->authorize('attachAppointment', $item->plan);

        $result = $this->treatmentPlanService->attachAppointment($request, $itemId);
        if ($result instanceof \Illuminate\Http\JsonResponse) return $result;
        return response()->json(['msg' => 'Treatment plan item attached to appointment successfully', 'status' => 200, 'data' => $result]);
    }
}
