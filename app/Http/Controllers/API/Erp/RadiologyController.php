<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\PatientRadiology;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RadiologyController extends Controller
{
    public function index(Request $request)
    {
        $customerId = $request->customer_id;

        $query = PatientRadiology::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('captured_at')
            ->orderByDesc('id');

        $radiologies = $query->get();

        return response()->json([
            'status' => 200,
            'data' => $radiologies,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Tenant::id();

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,pdf|max:20480',
            'file_type' => 'nullable|string|in:xray,panorama,cbct,cephalometric,report,consent,other',
            'tooth_number' => 'nullable|string|max:10',
            'captured_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$request->hasFile('file')) {
            return response()->json([
                'status' => 400,
                'message' => 'No file uploaded',
            ], 400);
        }

        $file = $request->file('file');

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $originalName) . '.' . $extension;

        // ✅ تم إزالة بادئة radiology/ لأن الديسك يتوجه إليها برمجياً تلقائياً
        $directory = "{$companyId}/{$request->customer_id}";

        Log::info('Upload attempt', [
            'directory' => $directory,
            'file_name' => $fileName,
        ]);

        // ✅ لورافيل سينشئ المجلدات الفرعية تلقائياً هنا داخل public/radiology/
        $filePath = $file->storeAs($directory, $fileName, 'public');

        if (!$filePath) {
            Log::error('Failed to save file', ['directory' => $directory, 'file_name' => $fileName]);
            return response()->json([
                'status' => 500,
                'message' => 'Failed to save file',
            ], 500);
        }

        Log::info('File saved successfully', ['path' => $filePath]);

        $radiology = PatientRadiology::create([
            'company_id' => $companyId,
            'branch_id'  => Tenant::branchId() ?? $request->header('X-Branch-ID'),
            'customer_id' => $request->customer_id,
            'dental_record_id' => $request->dental_record_id,
            'title' => $request->title,
            // سنخزن المسار مضافاً إليه radiology/ لكي يسهل على الـ Accessor قراءته وبنائه
            'file_path' => 'radiology/' . $filePath,
            'file_name' => $fileName,
            'file_type' => $request->file_type ?? 'xray',
            'tooth_number' => $request->tooth_number,
            'captured_at' => $request->captured_at ?? now(),
            'notes' => $request->notes,
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Radiology uploaded successfully',
            'data' => $radiology,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $radiology = PatientRadiology::query()
            ->where('id', $id)
            ->first();

        if (!$radiology) {
            return response()->json([
                'status' => 404,
                'message' => 'Radiology image not found',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => $radiology,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $radiology = PatientRadiology::query()->find($id);

        if (!$radiology) {
            return response()->json(['status' => 404, 'message' => 'Radiology image not found'], 404);
        }

        // ✅ تم تعديل ديسك الحذف ليعمل على الديسك الصحيح المباشر
        // نقوم بإزالة كلمة 'radiology/' من السلسلة النصية لأن جذر الديسك يبدأ منها أساساً
        $cleanPath = str_replace('radiology/', '', $radiology->file_path);

        if ($radiology->file_path && Storage::disk('radiology_public')->exists($cleanPath)) {
            Storage::disk('radiology_public')->delete($cleanPath);
        }

        $radiology->delete();

        return response()->json(['status' => 200, 'message' => 'Radiology image deleted successfully']);
    }
}
