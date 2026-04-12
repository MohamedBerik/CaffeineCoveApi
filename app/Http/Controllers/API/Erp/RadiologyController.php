<?php
// app/Http/Controllers/API/Erp/RadiologyController.php

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

        $query = PatientRadiology::query()
            ->where('company_id', $companyId)
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
        $companyId = $request->user()->company_id;

        // تعديل الـ validation
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx|max:10240', // إزالة 'image'
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

        // أضف debugging
        Log::info('Upload attempt', [
            'has_file' => $request->hasFile('file'),
            'file_name' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : null,
        ]);

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            // تأكد من صحة الملف
            if (!$file->isValid()) {
                return response()->json([
                    'status' => 400,
                    'message' => 'File is not valid: ' . $file->getError(),
                ], 400);
            }

            $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $directory = "radiology/{$companyId}/{$request->customer_id}";

            Log::info('Saving file', [
                'directory' => $directory,
                'file_name' => $fileName
            ]);

            $filePath = $file->storeAs($directory, $fileName, 'public');

            if (!$filePath) {
                return response()->json([
                    'status' => 500,
                    'message' => 'Failed to save file',
                ], 500);
            }

            Log::info('File saved successfully', ['path' => $filePath]);
        } else {
            return response()->json([
                'status' => 400,
                'message' => 'No file uploaded',
            ], 400);
        }

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
            'message' => 'Radiology image uploaded successfully',
            'data' => $radiology,
            'file_url' => Storage::disk('public')->url($filePath),
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
