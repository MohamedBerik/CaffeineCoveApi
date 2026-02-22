<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminCrudController extends Controller
{
    protected $allowedTables = [
        'users',
        'categories',
        'products',
        'customers',
        'orders',
        'employees',
        'sales',
        'reservations',
        'invoices',
        'suppliers',
        'purchase_orders',
        'companies',
    ];

    private function checkTable(string $table)
    {
        if (!in_array($table, $this->allowedTables)) {
            abort(404, 'Table not allowed');
        }

        if (!Schema::hasTable($table)) {
            abort(404, 'Table not found');
        }
    }

    private function applyCompanyScope($query, string $table, Request $request)
    {
        $user = $request->user();

        // ✅ super admin يرى كل الشركات (بدون فلترة)
        if ($user && $user->is_super_admin) {
            return $query;
        }

        // ✅ غير السوبر أدمن: فلترة على company_id
        if (Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    // =====================
    // GET ALL
    // =====================
    public function index(Request $request, string $table)
    {
        // $this->authorizeSuperAdmin($request);
        $this->checkTable($table);

        $query = DB::table($table);

        $query = $this->applyCompanyScope($query, $table, $request);

        $columns = Schema::getColumnListing($table);

        $hiddenColumns = ['password'];
        $selectColumns = array_diff($columns, $hiddenColumns);

        if ($request->filled('search')) {

            $search = $request->search;

            $searchableColumns = array_diff(
                $selectColumns,
                ['created_at', 'updated_at']
            );

            $query->where(function ($q) use ($searchableColumns, $search) {
                foreach ($searchableColumns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$search}%");
                }
            });
        }

        return response()->json(
            $query
                ->select($selectColumns)
                ->orderByDesc('id')
                ->paginate($request->get('per_page', 10))
        );
    }

    // =====================
    // GET ONE
    // =====================
    public function show(Request $request, string $table, int $id)
    {
        $this->checkTable($table);

        $query = DB::table($table);

        $query = $this->applyCompanyScope($query, $table, $request);

        $item = $query->where('id', $id)->first();

        if (!$item) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($item);
    }

    // =====================
    // CREATE
    // =====================
    public function store(Request $request, string $table)
    {
        $this->checkTable($table);

        $data = $request->except(['id', 'created_at', 'updated_at']);

        if (array_key_exists('password', $data) && !empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        if (Schema::hasColumn($table, 'company_id')) {
            if ($request->user()->is_super_admin) {
                // لو مش باعته company_id اعتبرها خطأ
                if (!isset($data['company_id'])) {
                    return response()->json(['message' => 'company_id is required'], 422);
                }
            } else {
                $data['company_id'] = $request->user()->company_id;
            }
        }

        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table($table)->insert($data);

        return response()->json([
            'success' => true,
            'message' => 'Created successfully'
        ], 201);
    }

    // =====================
    // UPDATE
    // =====================
    public function update(Request $request, string $table, int $id)
    {
        $this->checkTable($table);

        $user = $request->user();
        $data = $request->except(['id', 'created_at']); // شيلنا company_id من except

        if (array_key_exists('password', $data)) {
            if ($data['password']) {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']);
            }
        }

        $query = DB::table($table);

        if (Schema::hasColumn($table, 'company_id')) {

            // ✅ منع تعديل company_id تمامًا
            unset($data['company_id']);

            // ✅ فلترة البيانات حسب المستخدم
            if (!$user->isSuperAdmin()) {
                // Company Admin: يرى ويعدل فقط بيانات شركته
                $query->where('company_id', $user->company_id);
            }
            // Super Admin: لا يضاف شرط company_id (يرى كل البيانات)
        }

        $data['updated_at'] = now();

        $updated = $query->where('id', $id)->update($data);

        if (!$updated) {
            return response()->json(['message' => 'Not found or unauthorized'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Updated successfully'
        ]);
    }

    // =====================
    // DELETE
    // =====================
    public function destroy(Request $request, string $table, int $id)
    {
        $this->checkTable($table);

        $user = $request->user();
        $query = DB::table($table);

        // ✅ إضافة فلتر company_id إذا كان الجدول يدعمه
        if (Schema::hasColumn($table, 'company_id')) {

            if (!$user->isSuperAdmin()) {
                // Company Admin: يحذف فقط من شركته
                $query->where('company_id', $user->company_id);
            }
            // Super Admin: لا يضاف شرط company_id (يحذف من أي شركة)
        }

        // ✅ تنفيذ الحذف مع الشروط المطبقة
        $deleted = $query->where('id', $id)->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Record not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully'
        ]);
    }
}
