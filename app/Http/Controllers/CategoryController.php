<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;


class CategoryController extends Controller
{
    public function index()
    {
        $category = DB::table("categories")->select("cate_image","id","created_at","title_".app()->getLocale() . " as title","description_".app()->getLocale(). " as description")->get();
        return view('category.index', ["result" => $category]);
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        return view("Category.show", ["result" => $category]);
    }

    public function delete($id)
    {
        $category = Category::findOrFail($id);
        if (File::exists(public_path("/img/category/" . $category->cate_image))) {
            File::delete(public_path("/img/category/" . $category->cate_image));
        }


        $category->delete();
        return redirect()->route("dashboard")->with("categorymessage", "Deleted Successfully");
    }

    public function create()
    {
        return view("Category.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cate_image' => 'required|image|max:2048|mimes:png,jpeg',
            'id' => 'required|unique:categories|max:20',
            'title_en' => 'required|min:3|max:255',
            'title_ar' => 'required|min:3|max:255',
            'description_en' => 'required|min:3|max:255',
            'description_ar' => 'required|min:3|max:255',
        ]);

        if ($request->hasFile("cate_image")) {
            $image = $request->cate_image;
            $imageName = rand(1, 1000) . "_" . time() . "." . $image->extension();
            $image->move(public_path("/img/category/"), $imageName);
        }

        Category::create([
            "cate_image" => $imageName,
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
        ]);
        return redirect()->route("dashboard")->with("categorymessage", "Created Successfully");
    }

    public function edit($id)
    {
        $category = Category::findOrFail($id);
        return view("Category.edit", ["result" => $category]);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $category = Category::findOrFail($old_id);

        $request->validate([
            "cate_image" => "image|max:2048|mimes:png,jpeg",
            "id" => ['required', Rule::unique('categories')->ignore($old_id)],
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "required|min:3|max:255",
            "description_ar" => "required|min:3|max:255",
        ]);
        if ($request->hasFile("cate_image")) {
            $image = $request->cate_image;
            $imageName = rand(1, 1000) . "_" . time() . "." . $image->extension();

            if (File::exists(public_path("/img/category/" . $category->cate_image))) {
                File::delete(public_path("/img/category/" . $category->cate_image));
            }
            $image->move(public_path("/img/category/"), $imageName);
        } else {
            $imageName = $category->cate_image;
        }
        $category->update([
            "cate_image" => $imageName,
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
        ]);
        return redirect()->route("dashboard")->with("categorymessage", "Updated Successfully");
    }
}
