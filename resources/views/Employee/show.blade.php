@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>{{ __('Language.Employees') }}</h4>
                    <div>
                        <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                            <i class="fas fa-tachometer-alt"></i> {{ __('Language.Dashboard') }}
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th class="text-center">#</th>
                                    <th class="text-center">{{ __('Language.Name') }}</th>
                                    <th class="text-center">{{ __('Language.Email') }}</th>
                                    <th class="text-center">{{ __('Language.Salary') }}</th>
                                    <th class="text-center">{{ __('Language.Created_at') }}</th>
                                    <th class="text-center">{{ __('Language.Operation') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ $result->id }}</td>
                                    <td>{{ $result->name }}</td>
                                    <td>{{ $result->email }}</td>
                                    <td>{{ $result->salary }}</td>
                                    <td>{{ $result->created_at }}</td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('employee.index')}}" class="btn btn-sm btn-success">
                                                <i class="fas fa-house"></i>
                                            </a>
                                            <a href="{{ route('employee.edit', $result->id) }}" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('employee.delete', $result->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد؟')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
