@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-10 m-auto">
            <form method="post" action={{ route("employee.update") }} enctype="multipart/form-data">
                @csrf

                <input type="hidden" name="old_id" value={{$result->id}}>

                <label>ID</label>
                <input type="text" name="id" class="form-control mb-4" value={{ $result->id }}>
                @error('id')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <label>Name</label>
                <input type="text" name="name" class="form-control mb-4" value="{{ $result->name}}">
                @error('name')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <label>Email</label>
                <input type="email" name="email" class="form-control mb-4" value="{{ $result->email}}">
                @error('email')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <label>Password</label>
                <input type="password" name="password" class="form-control mb-4">
                @error('password')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <label>Salary</label>
                <input type="text" name="salary" class="form-control mb-4" value="{{ $result->salary}}">
                @error('salary')
                <h5 class="alert alert-danger">{{ $message }}</h5>
                @enderror

                <input type="submit" value="Update" class="btn btn-success d-block w-100">


            </form>
        </div>
    </div>
</div>
@endsection
