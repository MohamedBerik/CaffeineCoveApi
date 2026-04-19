<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Concerns\CompanyScope;
use App\Services\AdminCrudService;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCrudController extends Controller
{
    protected AdminCrudService $crudService;

    public function __construct(AdminCrudService $crudService)
    {
        $this->crudService = $crudService;
    }

    private function checkTable(string $table): void
    {
        if (!in_array($table, $this->crudService->getAllowedTables())) {
            abort(404, 'Table not allowed');
        }

        // ✅ منع Company Admin من الوصول لجدول companies
        if ($table === 'companies' && !Tenant::isSuperAdmin()) {
            abort(403, 'Unauthorized. Only Super Admin can access companies table.');
        }

        if (!DB::getSchemaBuilder()->hasTable($table)) {
            abort(404, 'Table not found');
        }
    }

    /**
     * ✅ التحقق من صلاحية الوصول للـ Table مع مراعاة الـ Action
     */
    private function authorizeTableAction(string $action, string $table, $recordId = null): void
    {
        $user = auth()->user();

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        // Super Admin → مسموح بكل حاجة
        if ($user->is_super_admin) {
            return;
        }

        // Company Admin → صلاحيات محددة
        if ($user->role === 'admin') {
            $companyId = Tenant::id();

            if (!$companyId) {
                abort(403, 'No tenant selected');
            }

            // ✅ Granular Permissions from Config (cached)
            $permissions = $this->crudService->getTablePermissions($table);

            if (!in_array($action, $permissions)) {
                abort(403, "Unauthorized. You don't have '{$action}' permission for '{$table}'.");
            }

            // ✅ لو فيه record معين، نتأكد إنه تبع شركته
            if ($recordId && $this->crudService->tableHasCompanyId($table)) {
                $record = DB::table($table)->where('id', $recordId)->first();

                if (!$record) {
                    abort(404, 'Record not found');
                }

                if (isset($record->company_id) && $record->company_id != $companyId) {
                    abort(403, 'Unauthorized. This record belongs to another company.');
                }
            }

            return;
        }

        // Regular user → ممنوع
        abort(403, 'Unauthorized. Admin access required.');
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

        if ($companyId && $this->crudService->tableHasCompanyId($table)) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    /**
     * GET /api/admin-crud/{table}
     */
    public function index(Request $request, string $table)
    {
        $this->authorizeTableAction('view', $table);
        $this->checkTable($table);

        $query = DB::table($table);
        $query = $this->applyTenantFilter($query, $table);

        $selectColumns = $this->crudService->getSafeColumns($table);

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
        $this->authorizeTableAction('view', $table, $id);
        $this->checkTable($table);

        $query = DB::table($table);
        $query = $this->applyTenantFilter($query, $table);

        $item = $query->where('id', $id)->first();

        if (!$item) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // إزالة الحقول الحساسة
        $safeColumns = $this->crudService->getSafeColumns($table);
        $safeItem = [];
        foreach ($safeColumns as $column) {
            if (property_exists($item, $column)) {
                $safeItem[$column] = $item->$column;
            }
        }

        return response()->json($safeItem);
    }

    /**
     * POST /api/admin-crud/{table}
     */
    public function store(Request $request, string $table)
    {
        $this->authorizeTableAction('create', $table);
        $this->checkTable($table);

        $data = $request->except(['id', 'created_at', 'updated_at']);

        // تشفير كلمة المرور لو موجودة
        if (array_key_exists('password', $data) && !empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        // ✅ التعامل مع company_id
        if ($this->crudService->tableHasCompanyId($table)) {
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

        // ✅ Clear cache for this table (new record added)
        $this->crudService->clearTableCache($table);

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
        $this->authorizeTableAction('update', $table, $id);
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
        $this->authorizeTableAction('delete', $table, $id);
        $this->checkTable($table);

        $query = DB::table($table);
        $query = $this->applyTenantFilter($query, $table);

        $deleted = $query->where('id', $id)->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Record not found or unauthorized'
            ], 404);
        }

        // ✅ Clear cache for this table (record deleted)
        $this->crudService->clearTableCache($table);

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
            $columns = $this->crudService->getSafeColumns($table);
            $searchableColumns = array_diff($columns, ['created_at', 'updated_at']);

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
