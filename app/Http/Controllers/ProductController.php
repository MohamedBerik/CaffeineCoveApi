<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $product = Product::all();
        return view('product.index', ["result" => $product]);
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        return view("Product.show", ["result" => $product]);
    }

    public function delete($id)
    {
        $product = Product::findOrFail($id);
        if (File::exists(public_path("/img/product/" . $product->product_image))) {
            File::delete(public_path("/img/product/" . $product->product_image));
        }
        $product->delete();
        return redirect()->route("dashboard")->with("productmessage", "Deleted Successfully");
    }

    public function create()
    {
        return view("Product.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
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
        if ($request->hasFile("product_image")) {
            $image = $request->product_image;
            $imageName = rand(1, 1000) . "_" . time() . "." . $image->extension();
            $image->move(public_path("/img/product/"), $imageName);
        }

        Product::create([
            "product_image" => $imageName,
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "price" => $request->price,
            "quantity" => $request->quantity,
            "category_id" => $request->category_id,
        ]);
        return redirect()->route("dashboard")->with("productmessage", "Created Successfully");
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        return view("Product.edit", ["result" => $product]);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $product = Product::findOrFail($old_id);

        $request->validate([
            "product_image" => "image|max:2048|mimes:png,jpeg",
            "id" => ['required', Rule::unique('products')->ignore($old_id)],
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "required|min:3|max:255",
            "description_ar" => "required|min:3|max:255",
            "price" => "required",
            "quantity" => "required",
            "category_id" => "category_id",
        ]);
        if ($request->hasFile("product_image")) {
            $image = $request->product_image;
            $imageName = rand(1, 1000) . "_" . time() . "." . $image->extension();

            if (File::exists(public_path("/img/product/" . $product->product_image))) {
                File::delete(public_path("/img/product/" . $product->product_image));
            }
            $image->move(public_path("/img/product/"), $imageName);
        } else {
            $imageName = $product->product_image;
        }
        $product->update([
            "product_image" => $imageName,
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "price" => $request->price,
            "quantity" => $request->quantity,
            "category_id" => $request->category_id,
        ]);
        return redirect()->route("dashboard")->with("productmessage", "Updated Successfully");
    }
}
