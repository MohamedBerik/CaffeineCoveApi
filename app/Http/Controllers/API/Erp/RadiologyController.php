<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\PatientRadiology;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RadiologyController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $customerId = $request->customer_id;

        // ✅ إضافة التحقق الأمني
        $query = PatientRadiology::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->orderByDesc('captured_at')
            ->orderByDesc('id');

        $radiologies = $query->get();

        // ✅ إضافة file_url لكل عنصر
        $radiologies->each(function ($radiology) {
            $radiology->file_url = $radiology->getFileUrlAttribute();
        });

        return response()->json([
            'status' => 200,
            'data' => $radiologies,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,pdf|max:10240',
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
            return response()->json(['status' => 400, 'message' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $directory = "radiology/{$companyId}/{$request->customer_id}";

        // ✅ المسار الكامل
        $fullPath = storage_path("app/public/{$directory}");

        // ✅ إنشاء المجلد إذا لم يكن موجوداً
        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        // ✅ نقل الملف
        $file->move($fullPath, $fileName);
        $filePath = "{$directory}/{$fileName}";

        Log::info('File saved', ['path' => $filePath, 'full_path' => $fullPath . '/' . $fileName]);

        $radiology = PatientRadiology::create([
            'company_id' => $companyId,
            'customer_id' => $request->customer_id,
            'dental_record_id' => $request->dental_record_id,
            'title' => $request->title,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_type' => $request->file_type ?? 'xray',
            'tooth_number' => $request->tooth_number,
            'captured_at' => $request->captured_at ?? now(),
            'notes' => $request->notes,
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Uploaded successfully',
            'data' => $radiology,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $radiology = PatientRadiology::query()
            ->where('company_id', $companyId)
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
        $companyId = $request->user()->company_id;

        $radiology = PatientRadiology::query()
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$radiology) {
            return response()->json([
                'status' => 404,
                'message' => 'Radiology image not found',
            ], 404);
        }

        $radiology->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Radiology image deleted successfully',
        ]);
    }
}
