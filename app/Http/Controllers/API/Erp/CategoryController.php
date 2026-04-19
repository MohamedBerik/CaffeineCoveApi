<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Category::class, 'category');
    }

    public function index(Request $request)
    {
        $categories = CategoryResource::collection(
            Category::query()->get()
        );

        return response()->json([
            "msg" => "Return All Data From Category Table",
            "status" => 200,
            "data" => $categories
        ]);
    }

    public function show(Request $request, $id)
    {
        $category = Category::query()->find($id);

        if ($category) {
            return response()->json([
                "msg" => "Return One Record of Category Table",
                "status" => 200,
                "data" => new CategoryResource($category)
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
        $category = Category::query()->find($id);

        if ($category) {
            if ($category->cate_image && File::exists(public_path("/img/category/" . $category->cate_image))) {
                File::delete(public_path("/img/category/" . $category->cate_image));
            }

            $category->delete();

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
            'description_en' => 'required|min:3|max:255',
            'description_ar' => 'required|min:3|max:255',
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $imageName = null;
        if ($request->hasFile("cate_image")) {
            $image = $request->cate_image;
            $imageName = rand(1, 1000) . "_" . time() . "." . $image->extension();
            $image->move(public_path("/img/category/"), $imageName);
        }

        $category = Category::create([
            "company_id" => Tenant::id(),
            "cate_image" => $imageName,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
        ]);

        return response()->json([
            "msg" => "Created Successfully",
            "status" => 201,
            "data" => new CategoryResource($category)
        ], 201);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $category = Category::query()->find($old_id);

        if (!$category) {
            return response()->json([
                "msg" => "No such id",
                "status" => 404,
                "data" => null
            ], 404);
        }

        $validate = Validator::make($request->all(), [
            "cate_image" => "nullable|image|max:2048|mimes:png,jpeg",
            "id" => [
                'required',
                Rule::unique('categories')->ignore($old_id),
            ],
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "required|min:3|max:255",
            "description_ar" => "required|min:3|max:255",
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $imageName = $category->cate_image;

        if ($request->hasFile("cate_image")) {
            $image = $request->cate_image;
            $imageName = rand(1, 1000) . "_" . time() . "." . $image->extension();

            if ($category->cate_image && File::exists(public_path("/img/category/" . $category->cate_image))) {
                File::delete(public_path("/img/category/" . $category->cate_image));
            }

            $image->move(public_path("/img/category/"), $imageName);
        }

        $category->update([
            "cate_image" => $imageName,
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
        ]);

        return response()->json([
            "msg" => "Updated Successfully",
            "status" => 200,
            "data" => new CategoryResource($category)
        ]);
    }
}
