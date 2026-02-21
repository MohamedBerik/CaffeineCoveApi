<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $product = ProductResource::collection(
            Product::where('company_id', $companyId)->get()
        );

        return response()->json([
            "msg" => "Return All Data From Product Table",
            "status" => 200,
            "data" => $product
        ]);
    }

    function show(Request $request, $id)
    {
        $product = Product::where('company_id', $request->user()->company_id)
            ->find($id);

        if ($product) {
            $data = [
                "msg" => "Return One Record of Product Table",
                "status" => 200,
                "data" => new ProductResource($product)
            ];
            return response()->json($data);
        } else {
            $data = [
                "msg" => "No Such id",
                "status" => 205,
                "data" => null
            ];
            return response()->json($data);
        }
    }
    function delete(Request $request)
    {
        $id = $request->id;
        $product = Product::where('company_id', $request->user()->company_id)
            ->find($id);
        if ($product) {
            // if (File::exists(public_path("/img/product/" . $product->product_image))) {
            //     File::delete(public_path("/img/product/" . $product->product_image));
            // }
            $product->delete();
            $data = [
                "msg" => "Deleted Successfully",
                "status" => 200,
                "data" => null
            ];
            return response()->json($data);
        } else {
            $data = [
                "msg" => "No Such id",
                "status" => 205,
                "data" => null
            ];
            return response()->json($data);
        }
    }
    public function store(Request $request)
    {

        $validate = Validator::make($request->all(), [
            'title_en' => 'required|min:3|max:255',
            'title_ar' => 'required|min:3|max:255',
            'description_en' => 'required|min:3|max:255',
            'description_ar' => 'required|min:3|max:255',
            'unit_price' => 'required|numeric',
            'category_id' => 'required|exists:categories,id',
            // 'product_image' => 'required|image|max:2048|mimes:png,jpeg',
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }

        // if ($request->hasFile("product_image")) {
        //     $image = $request->product_image;
        //     $imageName = rand(1, 1000) . "_" . time() . "." . $image->extension();
        //     $image->move(public_path("/img/product/"), $imageName);
        // }

        $product = Product::create([
            "company_id"     => $request->user()->company_id,
            "title_en"       => $request->title_en,
            "title_ar"       => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "unit_price"     => $request->unit_price,
            "stock_quantity" => 0,
            "category_id"    => $request->category_id,
            "quantity"       => $request->quantity,
            // "product_image"  => $imageName,
        ]);

        $data = [
            "msg" => "Created Successfully",
            "status" => 200,
            "data" => new ProductResource($product)
        ];
        return response()->json($data);
    }
    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $product = Product::where('company_id', $request->user()->company_id)
            ->find($old_id);

        $validate = Validator::make($request->all(), [
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "required|min:3|max:255",
            "description_ar" => "required|min:3|max:255",
            "unit_price" => "required",
            "category_id" => "required",
            "product_image" => "required|image|max:2048|mimes:png,jpeg",
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }

        if ($product) {

            $product->update([
                "title_en" => $request->title_en,
                "title_ar" => $request->title_ar,
                "description_en" => $request->description_en,
                "description_ar" => $request->description_ar,
                "unit_price" => $request->unit_price,
                // "stock_quantity" => $request->stock_quantity,
                "category_id" => $request->category_id,
            ]);
            $data = [
                "msg" => "Updated Successfully",
                "status" => 200,
                "data" => new ProductResource($product)
            ];
            return response()->json($data);
        } else {
            $data = [
                "msg" => "No such id",
                "status" => 205,
                "data" => null
            ];
            return response()->json($data);
        }
    }
}
