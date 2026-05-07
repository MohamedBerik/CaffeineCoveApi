<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcedureController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Procedure::class, 'procedure', [
            'except' => ['show', 'index']
        ]);
    }
    public function index(Request $request)
    {
        // ✅ تجاهل BranchScope لعرض جميع الإجراءات (مشتركة بين الفروع)
        $q = Procedure::withoutGlobalScope(\App\Models\Concerns\BranchScope::class)
            ->orderByDesc('id');

        if ($request->has('is_active')) {
            $isActive = filter_var(
                $request->query('is_active'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if (!is_null($isActive)) {
                $q->where('is_active', $isActive);
            }
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $q->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'msg' => 'Procedures list',
            'status' => 200,
            'data' => $q->paginate(20),
        ]);
    }

    public function show(Request $request, $id)
    {
        $procedure = Procedure::query()->findOrFail($id);

        return response()->json([
            'msg' => 'Procedure details',
            'status' => 200,
            'data' => $procedure,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Tenant::id();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:190',
                Rule::unique('procedures', 'name'),
            ],
            'default_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $procedure = Procedure::create([
            'company_id' => $companyId,
            'name' => trim($data['name']),
            'default_price' => (float) ($data['default_price'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return response()->json([
            'msg' => 'Procedure created',
            'status' => 201,
            'data' => $procedure,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $procedure = Procedure::query()->findOrFail($id);

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:190',
                Rule::unique('procedures', 'name')->ignore($procedure->id),
            ],
            'default_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $procedure->update([
            'name' => array_key_exists('name', $data) ? trim($data['name']) : $procedure->name,
            'default_price' => array_key_exists('default_price', $data)
                ? (float) ($data['default_price'] ?? 0)
                : $procedure->default_price,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : $procedure->is_active,
        ]);

        return response()->json([
            'msg' => 'Procedure updated',
            'status' => 200,
            'data' => $procedure->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $procedure = Procedure::query()->findOrFail($id);

        $procedure->update(['is_active' => false]);

        return response()->json([
            'msg' => 'Procedure deactivated',
            'status' => 200,
            'data' => $procedure->fresh(),
        ]);
    }
}
