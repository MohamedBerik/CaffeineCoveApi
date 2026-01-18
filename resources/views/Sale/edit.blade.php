@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-10 m-auto">
            <form method="post" action={{ route("sale.update") }} enctype="multipart/form-data">
                @csrf

                <input type="hidden" name="old_id" value={{$result->id}}>

                <label>ID</label>
                <input type="text" name="id" class="form-control mb-4" value={{ $result->id }}>
                @error('id')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <label>Title_en</label>
                <input type="text" name="title_en" class="form-control mb-4" value="{{ $result->title_en}}">
                @error('tite_en')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <label>Title_ar</label>
                <input type="text" name="title_ar" class="form-control mb-4" value="{{ $result->title_ar}}">
                @error('title_ar')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <label>Description_en</label>
                <input type="text" name="description_en" class="form-control mb-4" value="{{ $result->description_en}}">
                @error('description_en')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <label>Description_ar</label>
                <input type="text" name="description_ar" class="form-control mb-4" value="{{ $result->description_ar}}">
                @error('description_ar')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <label>Price</label>
                <input type="text" name="price" class="form-control mb-4" value="{{ $result->price}}">
                @error('price')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <label>Quantity</label>
                <input type="text" name="quantity" class="form-control mb-4" value="{{ $result->quantity}}">
                @error('quantity')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <label>Employee_id</label>
                <input type="text" name="employee_id" class="form-control mb-4" value="{{ $result->employee_id}}">
                @error('employee_id')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <input type="submit" value="Update" class="btn btn-success d-block w-100">


            </form>
        </div>
    </div>
</div>
@endsection
