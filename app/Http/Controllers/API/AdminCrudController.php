<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Concerns\CompanyScope;
use App\Services\Tenant;
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

    /**
     * ✅ تطبيق فلترة الـ Tenant باستخدام Tenant Service
     */
    private function applyTenantFilter($query, string $table)
    {
        // Super admin يرى كل الشركات
        if (Tenant::isSuperAdmin()) {
            return $query;
        }

        // مستخدم عادي - فلترة على company_id
        $companyId = Tenant::id();

        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    /**
     * GET /api/admin-crud/{table}
     */
    public function index(Request $request, string $table)
    {
        $this->checkTable($table);

        $query = DB::table($table);
        $query = $this->applyTenantFilter($query, $table);

        $columns = Schema::getColumnListing($table);
        $hiddenColumns = ['password', 'remember_token'];
        $selectColumns = array_diff($columns, $hiddenColumns);

        if ($request->filled('search')) {
            $search = $request->search;
            $searchableColumns = array_diff($selectColumns, ['created_at', 'updated_at']);

            $query->where(function ($q) use ($searchableColumns, $search) {
                foreach ($searchableColumns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$search}%");
                }
            });
        }

        // ✅ فلترة إضافية لو Super Admin عايز يشوف شركة معينة
        if (Tenant::isSuperAdmin() && $request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        return response()->json(
            $query
                ->select($selectColumns)
                ->orderByDesc('id')
                ->paginate($request->get('per_page', 10))
        );
    }

    /**
     * GET /api/admin-crud/{table}/{id}
     */
    public function show(Request $request, string $table, int $id)
    {
        $this->checkTable($table);

        $query = DB::table($table);
        $query = $this->applyTenantFilter($query, $table);

        $item = $query->where('id', $id)->first();

        if (!$item) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // إزالة الحقول الحساسة
        unset($item->password, $item->remember_token);

        return response()->json($item);
    }

    /**
     * POST /api/admin-crud/{table}
     */
    public function store(Request $request, string $table)
    {
        $this->checkTable($table);

        $data = $request->except(['id', 'created_at', 'updated_at']);

        // تشفير كلمة المرور لو موجودة
        if (array_key_exists('password', $data) && !empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        // ✅ التعامل مع company_id
        if (Schema::hasColumn($table, 'company_id')) {
            if (Tenant::isSuperAdmin()) {
                // Super Admin لازم يحدد company_id
                if (!isset($data['company_id'])) {
                    return response()->json(['message' => 'company_id is required'], 422);
                }
            } else {
                // مستخدم عادي - company_id من Tenant
                $data['company_id'] = Tenant::id();
            }
        }

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table($table)->insertGetId($data);

        return response()->json([
            'success' => true,
            'message' => 'Created successfully',
            'id' => $id
        ], 201);
    }

    /**
     * PUT /api/admin-crud/{table}/{id}
     */
    public function update(Request $request, string $table, int $id)
    {
        $this->checkTable($table);

        $data = $request->except(['id', 'created_at', 'company_id']); // ✅ منع تعديل company_id

        // تشفير كلمة المرور لو موجودة
        if (array_key_exists('password', $data)) {
            if ($data['password']) {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']);
            }
        }

        $query = DB::table($table);
        $query = $this->applyTenantFilter($query, $table);

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

    /**
     * DELETE /api/admin-crud/{table}/{id}
     */
    public function destroy(Request $request, string $table, int $id)
    {
        $this->checkTable($table);

        $query = DB::table($table);
        $query = $this->applyTenantFilter($query, $table);

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

    /**
     * ✅ إضافة: Super Admin يقدر يشوف كل بيانات جدول بدون فلترة
     */
    public function allCompanies(Request $request, string $table)
    {
        $this->checkTable($table);

        if (!Tenant::isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = DB::table($table);

        if ($request->filled('search')) {
            $search = $request->search;
            $columns = Schema::getColumnListing($table);
            $searchableColumns = array_diff($columns, ['password', 'created_at', 'updated_at']);

            $query->where(function ($q) use ($searchableColumns, $search) {
                foreach ($searchableColumns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$search}%");
                }
            });
        }

        return response()->json(
            $query->orderByDesc('id')->paginate($request->get('per_page', 10))
        );
    }
}
