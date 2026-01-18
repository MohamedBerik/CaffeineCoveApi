<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    function index()
    {
        $category = CategoryResource::collection(Category::all());
        $data = [
            "msg" => "Return All Data From Category Table",
            "status" => 200,
            "data" => $category
        ];
        return response()->json($data);
    }
    function show($id)
    {
        $category = Category::find($id);

        if ($category) {
            $data = [
                "msg" => "Return One Record of Category Table",
                "status" => 200,
                "data" => new CategoryResource($category)
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
        $category = Category::find($id);
        if ($category) {
            if (File::exists(public_path("/img/category/" . $category->cate_image))) {
                File::delete(public_path("/img/category/" . $category->cate_image));
            }
            $category->delete();
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
            'cate_image' => 'required|image|max:2048|mimes:png,jpeg',
            'id' => 'required|unique:categories|max:20',
            'title_en' => 'required|min:3|max:255',
            'title_ar' => 'required|min:3|max:255',
            'description_en' => 'required|min:3|max:255',
            'description_ar' => 'required|min:3|max:255',
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }

        if ($request->hasFile("cate_image")) {
            $image = $request->cate_image;
            $imageName = rand(1, 1000) . "_" . time() . "." . $image->extension();
            $image->move(public_path("/img/category/"), $imageName);
        }

        $category = Category::create([
            "cate_image" => $imageName,
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
        ]);
        $data = [
            "msg" => "Created Successfully",
            "status" => 200,
            "data" => new CategoryResource($category)
        ];
        return response()->json($data);
    }
    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $category = Category::find($old_id);

        $validate = Validator::make($request->all(), [
            "cate_image" => "image|max:2048|mimes:png,jpeg",
            "id" => ['required', Rule::unique('categories')->ignore($old_id)],
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "required|min:3|max:255",
            "description_ar" => "required|min:3|max:255",
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }






        if ($category) {

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
            $data = [
                "msg" => "Updated Successfully",
                "status" => 200,
                "data" => new CategoryResource($category)
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
