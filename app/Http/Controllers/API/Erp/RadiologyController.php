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

        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $originalName) . '.' . $extension;

            $directory = "{$companyId}/{$request->customer_id}";

            // الرفع على الديسك المخصص
            $filePath = $file->storeAs($directory, $fileName, 'radiology_public');

            if (!$filePath) {
                return response()->json([
                    'status' => 500,
                    'message' => 'Failed to save file on storage disk',
                ], 500);
            }

            // تحديد نوع الملف بشكل ذكي إذا لم يرسله الفرونت إند
            $finalFileType = $request->file_type;
            if (!$finalFileType) {
                $finalFileType = ($extension === 'pdf') ? PatientRadiology::TYPE_REPORT : PatientRadiology::TYPE_XRAY;
            }

            $radiology = PatientRadiology::create([
                'company_id' => $companyId,
                'branch_id'  => Tenant::branchId() ?? $request->header('X-Branch-ID'),
                'customer_id' => $request->customer_id,
                'dental_record_id' => $request->dental_record_id,
                'title' => $request->title,

                // التعديل هنا: نخزن الـ $filePath كما هو (1/39/file.jpg) لأن المجلد الأساسي مدمج بالديسك
                'file_path' => $filePath,

                'file_name' => $fileName,
                'file_type' => $finalFileType,
                'tooth_number' => $request->tooth_number,
                'captured_at' => $request->captured_at ?? now(),
                'notes' => $request->notes,
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Radiology file uploaded successfully',
                'data' => $radiology,
            ], 201);
        } catch (\Exception $e) {
            // في حال وجود مشكلة صلاحيات في مجلد التخزين المحلي، سيظهر لك السبب فوراً
            return response()->json([
                'status' => 500,
                'message' => 'Upload Exception: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $radiology = PatientRadiology::query()->find($id);

        if (!$radiology) {
            return response()->json(['status' => 404, 'message' => 'Radiology record not found'], 404);
        }

        // الحذف الآن أصبح معتمداً بالكامل على الـ Boot الخاص بالـ Model لمنع تكرار الكود
        $radiology->delete();

        return response()->json(['status' => 200, 'message' => 'Radiology record and file deleted successfully']);
    }
}
