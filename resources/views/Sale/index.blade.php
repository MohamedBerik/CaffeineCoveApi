@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>{{ __('Language.Sales') }}</h4>
                    <div>
                        <a href="{{ route('sale.create') }}" class="btn btn-success">
                            <i class="fas fa-plus"></i> {{ __('Language.CreateSale') }}
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
                                    <th class="text-center">#</th>
                                    <th class="text-center">{{ __('Language.Title') }}</th>
                                    <th class="text-center">{{ __('Language.Description') }}</th>
                                    <th class="text-center">{{ __('Language.Price') }}</th>
                                    <th class="text-center">{{ __('Language.Quantity') }}</th>
                                    <th class="text-center">{{ __('Language.Employee_id') }}</th>
                                    <th class="text-center">{{ __('Language.Operation') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($result as $sale)
                                <tr>
                                    <td>{{ $sale->id }}</td>
                                    <td>{{ $sale->title }}</td>
                                    <td>{{ $sale->description }}</td>
                                    <td>{{ $sale->price }}</td>
                                    <td>{{ $sale->quantity }}</td>
                                    <td>{{ $sale->employee_id }}</td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('sale.show', $sale->id) }}" class="btn btn-sm btn-success">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('sale.edit', $sale->id) }}" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('sale.delete', $sale->id) }}" method="POST" class="d-inline">
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
