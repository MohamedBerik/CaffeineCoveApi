@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>{{ __('Language.Customers') }}</h4>
                    <div>
                        <a href="{{ route('customer.create') }}" class="btn btn-success">
                            <i class="fas fa-plus"></i> {{ __('Language.CreateCustomer') }}
                        </a>
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
                                    <th>#</th>
                                    <th>{{ __('Language.Name') }}</th>
                                    <th>{{ __('Language.Email') }}</th>
                                    <th>{{ __('Language.Status') }}</th>
                                    <th>{{ __('Language.Created_at') }}</th>
                                    <th>{{ __('Language.Operation') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($result as $customer)
                                <tr>
                                    <td>{{ $customer->id }}</td>
                                    <td>{{ $customer->name }}</td>
                                    <td>{{ $customer->email }}</td>
                                    <td>
                                        <span class="badge bg-{{ $customer->status == 'active' ? 'success' : 'secondary' }}">
                                            {{ $customer->status }}
                                        </span>
                                    </td>
                                    <td>{{ $customer->created_at }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('customer.show', $customer->id) }}" class="btn btn-sm btn-success">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('customer.edit', $customer->id) }}" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('customer.delete', $customer->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد؟')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
