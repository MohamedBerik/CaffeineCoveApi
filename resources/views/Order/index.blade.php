@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>{{ __('Language.Orders') }}</h4>
                    <div>
                        <a href="{{ route('order.create') }}" class="btn btn-success">
                            <i class="fas fa-plus"></i> {{ __('Language.CreateOrder') }}
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
                                    <th>{{ __('Language.Title') }}</th>
                                    <th>{{ __('Language.Description') }}</th>
                                    <th>{{ __('Language.Price') }}</th>
                                    <th>{{ __('Language.Quantity') }}</th>
                                    <th>{{ __('Language.Customer_id') }}</th>
                                    <th>{{ __('Language.Operation') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($result as $order)
                                <tr>
                                    <td>{{ $order->id }}</td>
                                    <td>{{ $order->title }}</td>
                                    <td>{{ $order->description }}</td>
                                    <td>{{ $order->price }}</td>
                                    <td>{{ $order->quantity }}</td>
                                    <td>{{ $order->customer_id }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('order.show', $order->id) }}" class="btn btn-sm btn-success">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('order.edit', $order->id) }}" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('order.delete', $order->id) }}" method="POST" class="d-inline">
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
