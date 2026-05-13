<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Concerns\BranchScope;
use App\Models\Product;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Product::class, 'product', ['except' => ['index', 'show', 'update']]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Product::query()->orderByDesc('id');

        // عزل الفروع لغير المشرفين
        if (!$user->is_super_admin && $user->branch_id !== null) {
            $query->where('branch_id', $user->branch_id);
        }

        $products = ProductResource::collection($query->paginate((int)$request->get('per_page', 20)));

        return response()->json([
            "msg" => "Products list",
            "status" => 200,
            "data" => $products
        ]);
    }

    public function show(Request $request, $id)
    {
        $product = Product::withoutGlobalScope(BranchScope::class)
            ->where('company_id', Tenant::id())
            ->findOrFail($id);

        $this->authorize('view', $product);

        return response()->json([
            "msg" => "Product details",
            "status" => 200,
            "data" => new ProductResource($product)
        ]);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'title_en' => 'required|min:3|max:255',
            'title_ar' => 'required|min:3|max:255',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'unit_price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'product_image' => 'nullable|image|max:2048|mimes:png,jpeg,jpg',
            'quantity' => 'nullable|integer|min:0',
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $imageName = null;
        if ($request->hasFile("product_image")) {
            $image = $request->product_image;
            $imageName = rand(1, 1000) . "_" . time() . "." . $image->extension();
            $image->move(public_path("/img/product/"), $imageName);
        }

        $product = Product::create([
            "company_id"     => Tenant::id(),
            "branch_id"      => Tenant::branchId() ?? $request->user()->branch_id,
            "title_en"       => $request->title_en,
            "title_ar"       => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "unit_price"     => $request->unit_price,
            "stock_quantity" => $request->quantity ?? 0,
            "quantity"       => $request->quantity ?? 0,
            "category_id"    => $request->category_id,
            "product_image"  => $imageName,
        ]);

        return response()->json([
            "msg" => "Product created successfully",
            "status" => 201,
            "data" => new ProductResource($product)
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::withoutGlobalScope(BranchScope::class)
            ->where('company_id', Tenant::id())
            ->findOrFail($id);

        $this->authorize('update', $product);

        $validate = Validator::make($request->all(), [
            "title_en" => "sometimes|required|min:3|max:255",
            "title_ar" => "sometimes|required|min:3|max:255",
            "description_en" => "nullable|string",
            "description_ar" => "nullable|string",
            "unit_price" => "sometimes|required|numeric|min:0",
            "category_id" => "sometimes|required|exists:categories,id",
            "product_image" => "nullable|image|max:2048|mimes:png,jpeg,jpg",
            "quantity" => "nullable|integer|min:0",
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $imageName = $product->product_image;

        if ($request->hasFile("product_image")) {
            $image = $request->product_image;
            $imageName = rand(1, 1000) . "_" . time() . "." . $image->extension();

            if ($product->product_image && File::exists(public_path("/img/product/" . $product->product_image))) {
                File::delete(public_path("/img/product/" . $product->product_image));
            }

            $image->move(public_path("/img/product/"), $imageName);
        }

        $product->update([
            "title_en" => $request->title_en ?? $product->title_en,
            "title_ar" => $request->title_ar ?? $product->title_ar,
            "description_en" => $request->description_en ?? $product->description_en,
            "description_ar" => $request->description_ar ?? $product->description_ar,
            "unit_price" => $request->unit_price ?? $product->unit_price,
            "stock_quantity" => $request->quantity ?? $product->stock_quantity,
            "quantity" => $request->quantity ?? $product->quantity,
            "category_id" => $request->category_id ?? $product->category_id,
            "product_image" => $imageName,
        ]);

        return response()->json([
            "msg" => "Product updated successfully",
            "status" => 200,
            "data" => new ProductResource($product)
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $product = Product::withoutGlobalScope(BranchScope::class)
            ->where('company_id', Tenant::id())
            ->findOrFail($id);

        $this->authorize('delete', $product);

        if ($product->product_image && File::exists(public_path("/img/product/" . $product->product_image))) {
            File::delete(public_path("/img/product/" . $product->product_image));
        }
        $product->delete();

        return response()->json([
            "msg" => "Product deleted successfully",
            "status" => 200,
            "data" => null
        ]);
    }
}
