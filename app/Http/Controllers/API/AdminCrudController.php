<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminCrudController extends Controller
{
    /**
     * الجداول المسموح التعامل معها فقط
     */
    protected $allowedTables = [
        'users',
        'categories',
        'products',
        'customers',
        'orders',
        'employees',
        'sales',
        'reservations',
        'invoices'
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

    // =====================
    // GET ALL
    // =====================
    public function index(Request $request, string $table)
    {
        $this->checkTable($table);

        $query = DB::table($table);

        $columns = Schema::getColumnListing($table);

        // 🚫 Hide sensitive columns
        $hiddenColumns = ['password'];
        $selectColumns = array_diff($columns, $hiddenColumns);

        // 🔍 Search
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

        $item = DB::table($table)->where('id', $id)->first();

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

        // 🔐 Hash any password field automatically
        if (array_key_exists('password', $data) && !empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        if (array_key_exists('status', $data) && ($data['status'] === null || $data['status'] === '')) {
            unset($data['status']);
        }
        if (array_key_exists('role', $data) && ($data['role'] === null || $data['role'] === '')) {
            unset($data['role']);
        }

        // timestamps يدويًا
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

        $data = $request->except(['id', 'created_at']);

        // 🔐 Hash password if exists
        if (array_key_exists('password', $data)) {
            if ($data['password']) {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']); // لو فاضي ما نحدّثوش
            }
        }

        // update timestamp
        $data['updated_at'] = now();

        DB::table($table)
            ->where('id', $id)
            ->update($data);

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

        DB::table($table)->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully'
        ]);
    }
}
