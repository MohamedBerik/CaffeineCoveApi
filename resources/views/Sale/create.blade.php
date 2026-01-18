@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-10 m-auto">

            <form method="post" action={{ route("sale.store") }} enctype="multipart/form-data">
                @csrf

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

                <label>Price</label>
                <input type="text" name="price" class="form-control mb-4">
                @error('price')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <label>Quantity</label>
                <input type="text" name="quantity" class="form-control mb-4">
                @error('quantity')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <label>Employee_id</label>
                <input type="text" name="employee_id" class="form-control mb-4">
                @error('employee_id')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <input type="submit" value="Create New Order" class="btn btn-success d-block w-100">
            </form>
        </div>
    </div>
</div>
@endsection
