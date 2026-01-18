@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-10 m-auto">

            <form method="post" action={{ route("category.store") }} enctype="multipart/form-data">
                @csrf
                <label>Image</label>
                <input type="file" name="cate_image" class="form-control mb-4">
                @error('cate_image')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <label>ID</label>
                <input type="text" name="id" class="form-control mb-4">
                @error('id')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <label>Title_en</label>
                <input type="text" name="title_en" class="form-control mb-4">
                @error('title_en')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror


                <label>Title_ar</label>
                <input type="text" name="title_ar" class="form-control mb-4">
                @error('title_ar')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror


                <label>Description_en</label>
                <input type="text" name="description_en" class="form-control mb-4">
                @error('description_en')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror


                <label>Description_ar</label>
                <input type="text" name="description_ar" class="form-control mb-4">
                @error('description_ar')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror


                <input type="submit" value="Create New Category" class="btn btn-success d-block w-100">
            </form>
        </div>
    </div>
</div>
@endsection
