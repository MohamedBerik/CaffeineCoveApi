<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = ProductResource::collection(
            Product::query()->get()
        );

        return response()->json([
            "msg" => "Return All Data From Product Table",
            "status" => 200,
            "data" => $products
        ]);
    }

    public function show(Request $request, $id)
    {
        $product = Product::query()->find($id);

        if ($product) {
            return response()->json([
                "msg" => "Return One Record of Product Table",
                "status" => 200,
                "data" => new ProductResource($product)
            ]);
        }

        return response()->json([
            "msg" => "No Such id",
            "status" => 404,
            "data" => null
        ], 404);
    }

    public function delete(Request $request)
    {
        $id = $request->id;
        $product = Product::query()->find($id);

        if ($product) {
            if ($product->product_image && File::exists(public_path("/img/product/" . $product->product_image))) {
                File::delete(public_path("/img/product/" . $product->product_image));
            }
            $product->delete();

            return response()->json([
                "msg" => "Deleted Successfully",
                "status" => 200,
                "data" => null
            ]);
        }

        return response()->json([
            "msg" => "No Such id",
            "status" => 404,
            "data" => null
        ], 404);
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
            "title_en"       => $request->title_en,
            "title_ar"       => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "unit_price"     => $request->unit_price,
            "stock_quantity" => $request->quantity ?? 0,
            "category_id"    => $request->category_id,
            "product_image"  => $imageName,
        ]);

        return response()->json([
            "msg" => "Created Successfully",
            "status" => 201,
            "data" => new ProductResource($product)
        ], 201);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $product = Product::query()->find($old_id);

        if (!$product) {
            return response()->json([
                "msg" => "No such id",
                "status" => 404,
                "data" => null
            ], 404);
        }

        $validate = Validator::make($request->all(), [
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "nullable|string",
            "description_ar" => "nullable|string",
            "unit_price" => "required|numeric|min:0",
            "category_id" => "required|exists:categories,id",
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
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "unit_price" => $request->unit_price,
            "stock_quantity" => $request->quantity ?? $product->stock_quantity,
            "category_id" => $request->category_id,
            "product_image" => $imageName,
        ]);

        return response()->json([
            "msg" => "Updated Successfully",
            "status" => 200,
            "data" => new ProductResource($product)
        ]);
    }
}
