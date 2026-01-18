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
    function index()
    {
        $product = ProductResource::collection(Product::all());
        $data = [
            "msg" => "Return All Data From Product Table",
            "status" => 200,
            "data" => $product
        ];
        return response()->json($data);
    }
    function show($id)
    {
        $product = Product::find($id);

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
        $product = Product::find($id);
        if ($product) {
            if (File::exists(public_path("/img/product/" . $product->product_image))) {
                File::delete(public_path("/img/product/" . $product->product_image));
            }
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
            'product_image' => 'required|image|max:2048|mimes:png,jpeg',
            'id' => 'required|unique:products|max:20',
            'title_en' => 'required|min:3|max:255',
            'title_ar' => 'required|min:3|max:255',
            'description_en' => 'required|min:3|max:255',
            'description_ar' => 'required|min:3|max:255',
            'price' => 'required',
            'quantity' => 'required',
            'category_id' => 'required',
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }

        if ($request->hasFile("product_image")) {
            $image = $request->product_image;
            $imageName = rand(1, 1000) . "_" . time() . "." . $image->extension();
            $image->move(public_path("/img/product/"), $imageName);
        }

        $product = Product::create([
            "cate_image" => $imageName,
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "price" => $request->price,
            "quantity" => $request->quantity,
            "category_id" => $request->category_id,
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
        $product = Product::find($old_id);

        $validate = Validator::make($request->all(), [
            "product_image" => "image|max:2048|mimes:png,jpeg",
            "id" => ['required', Rule::unique('products')->ignore($old_id)],
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "required|min:3|max:255",
            "description_ar" => "required|min:3|max:255",
            "price" => "required",
            "quantity" => "required",
            "category_id" => "required",
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
                "id" => $request->id,
                "title_en" => $request->title_en,
                "title_ar" => $request->title_ar,
                "description_en" => $request->description_en,
                "description_ar" => $request->description_ar,
                "price" => $request->price,
                "quantity" => $request->quantity,
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
