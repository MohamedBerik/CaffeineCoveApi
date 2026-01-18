@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <!-- Statistics Cards -->

        <div class="row mb-4">
            <!-- Users Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    {{ __('Language.Users') }}
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $users->count() }}</div>
                                <div class="mt-2">
                                    <a href="{{ route('user.create') }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> {{ __('Language.CreateUser') }}
                                    </a>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-secondary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                    {{ __('Language.Categories') }}
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $categories->count() }}</div>
                                <div class="mt-2">
                                    <a href="{{ route('category.create') }}" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-plus"></i> {{ __('Language.CreateCategory') }}
                                    </a>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-folder fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    {{ __('Language.Products') }}
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $products->count() }}</div>
                                <div class="mt-2">
                                    <a href="{{ route('product.create') }}" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus"></i> {{ __('Language.CreateProduct') }}
                                    </a>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-box fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customers Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    {{ __('Language.Customers') }}
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $customers->count() }}</div>
                                <div class="mt-2">
                                    <a href="{{ route('customer.create') }}" class="btn btn-sm btn-warning text-white">
                                        <i class="fas fa-plus"></i> {{ __('Language.CreateCustomer') }}
                                    </a>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-friends fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    {{ __('Language.Orders') }}
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $orders->count() }}</div>
                                <div class="mt-2">
                                    <a href="{{ route('order.create') }}" class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-plus"></i> {{ __('Language.CreateOrder') }}
                                    </a>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employees Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    {{ __('Language.Employees') }}
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $employees->count() }}</div>
                                <div class="mt-2">
                                    <a href="{{ route('employee.create') }}" class="btn btn-sm btn-danger">
                                        <i class="fas fa-plus"></i> {{ __('Language.CreateEmployee') }}
                                    </a>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-briefcase fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-dark shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">
                                    {{ __('Language.Sales') }}
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $sales->count() }}</div>
                                <div class="mt-2">
                                    <a href="{{ route('sale.create') }}" class="btn btn-sm btn-dark">
                                        <i class="fas fa-plus"></i> {{ __('Language.CreateSale') }}
                                    </a>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Revenue Card (مثال) -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    {{ __('Language.Revenue') }}
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    @php
                                        $totalRevenue = $orders->sum('price') + $sales->sum('price');
                                        echo number_format($totalRevenue) . ' $';
                                    @endphp
                                </div>
                                <div class="mt-2">
                                    <span class="text-success">
                                        <i class="fas fa-chart-line"></i>{{ __('Language.Total Sales') }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4" id="charts-section">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-bar me-2"></i> {{ __('Language.Analytics Analysis') }}
                        </h6>
                        <button class="btn btn-sm btn-primary" onclick="showCharts()">
                            <i class="fas fa-expand"></i> {{ __('Language.Show All') }}
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="chart-placeholder" style="height: 300px;">
                                    <div class="text-center py-5">
                                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">رسم بياني للإحصائيات</p>
                                        <small>يمكن إضافة Chart.js هنا مستقبلاً</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="chart-placeholder" style="height: 300px;">
                                    <div class="text-center py-5">
                                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">رسم بياني للمبيعات</p>
                                        <small>يمكن إضافة Chart.js هنا مستقبلاً</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Overview Tables -->
        <div class="row">
            <!-- Users Table -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-users me-2"></i>{{ __('Language.Users') }}
                            <span class="badge bg-primary ms-2">{{ $users->count() }}</span>
                        </h6>
                        <div>
                            <a href="{{ route('user.create') }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> {{ __('Language.New') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th class="text-center">{{ __('Language.Name') }}</th>
                                        <th class="text-center">{{ __('Language.Email') }}</th>
                                        <th class="text-center">{{ __('Language.Role') }}</th>
                                        <th class="text-center">{{ __('Language.Status') }}</th>
                                        <th class="text-center">{{ __('Language.Operation') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($users->take(5) as $item)
                                        <tr>
                                            <td>{{ $item->id }}</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle-sm bg-primary text-white me-2">
                                                        {{ strtoupper(substr($item->name, 0, 1)) }}
                                                    </div>
                                                    {{ $item->name }}
                                                </div>
                                            </td>
                                            <td>{{ $item->email }}</td>
                                            <td>
                                                <span class="badge bg-{{ $item->role == 'admin' ? 'danger' : 'info' }}">
                                                    {{ $item->role }}
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $item->status == 'active' ? 'success' : 'secondary' }}">
                                                    {{ $item->status }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('user.show', $item->id) }}"
                                                        class="btn btn-outline-success">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('user.edit', $item->id) }}"
                                                        class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('user.delete', $item->id) }}" method="POST"
                                                        class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger"
                                                            onclick="return confirm('هل أنت متأكد؟')">
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
                        @if ($users->count() > 5)
                            <div class="card-footer text-center">
                                <a href="{{ route('user.index') }}" class="text-primary" onclick="showAllUsers()">
                                    <i class="fas fa-arrow-down me-1"></i>{{ __('Language.All Users') }}
                                    ({{ $users->count() }})
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Categories Table -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-secondary">
                            <i class="fas fa-folder me-2"></i>{{ __('Language.Categories') }}
                            <span class="badge bg-secondary ms-2">{{ $categories->count() }}</span>
                        </h6>
                        <div>
                            <a href="{{ route('category.create') }}" class="btn btn-sm btn-secondary">
                                <i class="fas fa-plus"></i> {{ __('Language.New') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th class="text-center">{{ __('Language.Image') }}</th>
                                        <th class="text-center">{{ __('Language.Title') }}</th>
                                        <th class="text-center">{{ __('Language.Description') }}</th>
                                        <th class="text-center">{{ __('Language.Operation') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($categories->take(5) as $item)
                                        <tr>
                                            <td class="text-center">{{ $item->id }}</td>
                                            <td>
                                                @if ($item->cate_image)
                                                    <img src="{{ asset('/img/category/' . $item->cate_image) }}"
                                                        class="img-thumbnail"
                                                        style="width: 50px; height: 50px; object-fit: cover;"
                                                        alt="{{ $item->title }}">
                                                @else
                                                    <div class="avatar-circle-sm bg-secondary text-white">
                                                        {{ strtoupper(substr($item->title, 0, 1)) }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>{{ Str::limit($item->title, 25) }}</td>
                                            <td class="text-center">{{ $item->description }}</td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('category.show', $item->id) }}"
                                                        class="btn btn-outline-secondary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('category.edit', $item->id) }}"
                                                        class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('category.delete', $item->id) }}"
                                                        method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger"
                                                            onclick="return confirm('هل أنت متأكد؟')">
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
                        @if ($categories->count() > 5)
                            <div class="card-footer text-center">
                                <a href="{{ route('category.index') }}" class="text-secondary"
                                    onclick="showAllCategories()">
                                    <i class="fas fa-arrow-down me-1"></i>{{ __('Language.All Categories') }}
                                    ({{ $categories->count() }})
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-success">
                            <i class="fas fa-box me-2"></i>{{ __('Language.Products') }}
                            <span class="badge bg-success ms-2">{{ $products->count() }}</span>
                        </h6>
                        <div>
                            <a href="{{ route('product.create') }}" class="btn btn-sm btn-success">
                                <i class="fas fa-plus"></i> {{ __('Language.New') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>{{ __('Language.Image') }}</th>
                                        <th>{{ __('Language.Title') }}</th>
                                        <th>{{ __('Language.Price') }}</th>
                                        <th>{{ __('Language.Quantity') }}</th>
                                        <th>{{ __('Language.Category_id') }}</th>
                                        <th class="text-center">{{ __('Language.Operation') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($products->take(5) as $item)
                                        <tr>
                                            <td class="text-center">{{ $item->id }}</td>
                                            <td>
                                                @if ($item->product_image)
                                                    <img src="{{ asset('/img/product/' . $item->product_image) }}"
                                                        class="img-thumbnail"
                                                        style="width: 50px; height: 50px; object-fit: cover;"
                                                        alt="{{ $item->title }}">
                                                @else
                                                    <div class="avatar-circle-sm bg-success text-white">
                                                        {{ strtoupper(substr($item->title, 0, 1)) }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>{{ Str::limit($item->title, 20) }}</td>
                                            <td>
                                                <span class="fw-bold text-success">{{ $item->price }} $</span>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $item->quantity > 10 ? 'success' : ($item->quantity > 0 ? 'warning' : 'danger') }}">
                                                    {{ $item->quantity }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $item->category_id }}</span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('product.show', $item->id) }}"
                                                        class="btn btn-outline-success">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('product.edit', $item->id) }}"
                                                        class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('product.delete', $item->id) }}"
                                                        method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger"
                                                            onclick="return confirm('هل أنت متأكد؟')">
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
                        @if ($products->count() > 5)
                            <div class="card-footer text-center">
                                <a href="javascript:void(0)" class="text-success" onclick="showAllProducts()">
                                    <i class="fas fa-arrow-down me-1"></i>{{ __('Language.All Products') }}
                                    ({{ $products->count() }})
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-warning">
                            <i class="fas fa-user-friends me-2"></i>{{ __('Language.Customers') }}
                            <span class="badge bg-warning ms-2">{{ $customers->count() }}</span>
                        </h6>
                        <div>
                            <a href="{{ route('customer.create') }}" class="btn btn-sm btn-warning text-white">
                                <i class="fas fa-plus"></i> {{ __('Language.New') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Language.Name') }}</th>
                                        <th>{{ __('Language.Email') }}</th>
                                        <th>{{ __('Language.Status') }}</th>
                                        <th class="text-center">{{ __('Language.Operation') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($customers->take(5) as $item)
                                        <tr>
                                            <td>{{ $item->id }}</td>
                                            <td>{{ $item->name }}</td>
                                            <td>{{ $item->email }}</td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $item->status == 'active' ? 'success' : 'secondary' }}">
                                                    {{ $item->status }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('customer.show', $item->id) }}"
                                                        class="btn btn-outline-warning">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('customer.edit', $item->id) }}"
                                                        class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('customer.delete', $item->id) }}"
                                                        method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger"
                                                            onclick="return confirm('هل أنت متأكد؟')">
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
                        @if ($customers->count() > 5)
                            <div class="card-footer text-center">
                                <a href="javascript:void(0)" class="text-warning" onclick="showAllCustomers()">
                                    <i class="fas fa-arrow-down me-1"></i>{{ __('Language.All Customers') }}
                                    ({{ $customers->count() }})
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-info">
                            <i class="fas fa-shopping-cart me-2"></i>{{ __('Language.Orders') }}
                            <span class="badge bg-info ms-2">{{ $orders->count() }}</span>
                        </h6>
                        <div>
                            <a href="{{ route('order.create') }}" class="btn btn-sm btn-info text-white">
                                <i class="fas fa-plus"></i> {{ __('Language.New') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Language.Title') }}</th>
                                        <th>{{ __('Language.Description') }}</th>
                                        <th>{{ __('Language.Price') }}</th>
                                        <th>{{ __('Language.Quantity') }}</th>
                                        <th>{{ __('Language.Customer_id') }}</th>
                                        <th class="text-center">{{ __('Language.Operation') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($orders->take(5) as $item)
                                        <tr>
                                            <td>{{ $item->id }}</td>
                                            <td>{{ Str::limit($item->title, 15) }}</td>
                                            <td>{{ Str::limit($item->description, 30) }}</td>
                                            <td>
                                                <span class="fw-bold text-info">{{ $item->price }} $</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">{{ $item->quantity }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $item->customer_id }}</span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('order.show', $item->id) }}"
                                                        class="btn btn-outline-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('order.edit', $item->id) }}"
                                                        class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('order.delete', $item->id) }}" method="POST"
                                                        class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger"
                                                            onclick="return confirm('هل أنت متأكد؟')">
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
                        @if ($orders->count() > 5)
                            <div class="card-footer text-center">
                                <a href="javascript:void(0)" class="text-info" onclick="showAllOrders()">
                                    <i class="fas fa-arrow-down me-1"></i>{{ __('Language.All Orders') }}
                                    ({{ $orders->count() }})
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Employees Table -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-danger">
                            <i class="fas fa-briefcase me-2"></i>{{ __('Language.Employees') }}
                            <span class="badge bg-danger ms-2">{{ $employees->count() }}</span>
                        </h6>
                        <div>
                            <a href="{{ route('employee.create') }}" class="btn btn-sm btn-danger">
                                <i class="fas fa-plus"></i> {{ __('Language.New') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Language.Name') }}</th>
                                        <th>{{ __('Language.Email') }}</th>
                                        <th>{{ __('Language.Salary') }}</th>
                                        <th class="text-center">{{ __('Language.Operation') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($employees->take(5) as $item)
                                        <tr>
                                            <td>{{ $item->id }}</td>
                                            <td>{{ $item->name }}</td>
                                            <td>{{ $item->email }}</td>
                                            <td>
                                                <span class="fw-bold text-danger">{{ $item->salary }} $</span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('employee.show', $item->id) }}"
                                                        class="btn btn-outline-danger">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('employee.edit', $item->id) }}"
                                                        class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('employee.delete', $item->id) }}"
                                                        method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger"
                                                            onclick="return confirm('هل أنت متأكد؟')">
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
                        @if ($employees->count() > 5)
                            <div class="card-footer text-center">
                                <a href="javascript:void(0)" class="text-danger" onclick="showAllEmployees()">
                                    <i class="fas fa-arrow-down me-1"></i>{{ __('Language.All Employees') }}
                                    ({{ $employees->count() }})
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-dark">
                            <i class="fas fa-chart-line me-2"></i>{{ __('Language.Sales') }}
                            <span class="badge bg-dark ms-2">{{ $sales->count() }}</span>
                        </h6>
                        <div>
                            <a href="{{ route('sale.create') }}" class="btn btn-sm btn-dark">
                                <i class="fas fa-plus"></i> {{ __('Language.New') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Language.Title') }}</th>
                                        <th>{{ __('Language.Description') }}</th>
                                        <th>{{ __('Language.Price') }}</th>
                                        <th>{{ __('Language.Quantity') }}</th>
                                        <th>{{ __('Language.Employee_id') }}</th>
                                        <th class="text-center">{{ __('Language.Operation') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sales->take(5) as $item)
                                        <tr>
                                            <td>{{ $item->id }}</td>
                                            <td>{{ Str::limit($item->title, 20) }}</td>
                                            <td>{{ Str::limit($item->description, 30) }}</td>
                                            <td>
                                                <span class="fw-bold text-dark">{{ $item->price }} $</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">{{ $item->quantity }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $item->employee_id }}</span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('sale.show', $item->id) }}"
                                                        class="btn btn-outline-dark">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('sale.edit', $item->id) }}"
                                                        class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('sale.delete', $item->id) }}" method="POST"
                                                        class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger"
                                                            onclick="return confirm('هل أنت متأكد؟')">
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
                        @if ($sales->count() > 5)
                            <div class="card-footer text-center">
                                <a href="javascript:void(0)" class="text-dark" onclick="showAllSales()">
                                    <i class="fas fa-arrow-down me-1"></i>{{ __('Language.All Sales') }}
                                    ({{ $sales->count() }})
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Reservations Table -->
            <div class="col-lg-6 mb-4">
                @if (session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-dark">
                            <i class="fas fa-chart-line me-2"></i>{{ __('Language.Reservations') }}
                            <span class="badge bg-dark ms-2">{{ $reservations->count() }}</span>
                        </h6>
                        <div>
                            <a href="#" class="btn btn-sm btn-dark">
                                <i class="fas fa-plus"></i> {{ __('Language.New') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Language.Name') }}</th>
                                        <th>{{ __('Language.Email') }}</th>
                                        <th>{{ __('Language.Persons') }}</th>
                                        <th>{{ __('Language.Date') }}</th>
                                        <th>{{ __('Language.Time') }}</th>
                                        <th>{{ __('Language.Message') }}</th>
                                        <th>{{ __('Language.Status') }}</th>
                                        <th>{{ __('Language.Action') }}</th>
                                        <th class="text-center">{{ __('Language.Operation') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reservations->take(5) as $item)
                                        <tr>
                                            <td>{{ $item->id }}</td>
                                            <td>{{ Str::limit($item->name, 20) }}</td>
                                            <td>{{ Str::limit($item->email, 30) }}</td>
                                            <td>
                                                <span class="fw-bold text-dark">{{ $item->persons }} $</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">{{ $item->date }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $item->time }}</span>
                                            </td>
                                            <td>{{ $item->message }}</td>
                                            <td>
                                                <span
                                                    class="
                    badge
                    {{ $item->status == 'confirmed' ? 'bg-success' : '' }}
                    {{ $item->status == 'cancelled' ? 'bg-danger' : '' }}
                    {{ $item->status == 'pending' ? 'bg-warning' : '' }}
                ">
                                                    {{ ucfirst($item->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($item->status == 'pending')
                                                    <form action="{{ route('reservations.confirm', $item->id) }}"
                                                        method="POST" style="display:inline">
                                                        @csrf
                                                        <button class="btn btn-sm btn-success">Confirm</button>
                                                    </form>

                                                    <form action="{{ route('reservations.cancel', $item->id) }}"
                                                        method="POST" style="display:inline">
                                                        @csrf
                                                        <button class="btn btn-sm btn-danger">Cancel</button>
                                                    </form>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="#" class="btn btn-outline-dark">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    {{-- <a href="#" class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a> --}}
                                                    <form action="{{ route('reservations.delete', $item->id) }}"
                                                        method="POST" class="d-inline"
                                                        onsubmit="return confirm('Are you sure you want to delete this reservation?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger">
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
                        @if ($reservations->count() > 5)
                            <div class="card-footer text-center">
                                <a href="{{ route('reservations.index') }}" class="text-dark"
                                    onclick="showAllReservations()">
                                    <i class="fas fa-arrow-down me-1"></i>{{ __('Language.All Reservations') }}
                                    ({{ $reservations->count() }})
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Charts -->
    <div class="modal fade" id="chartsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-chart-bar me-2"></i>الرسوم البيانية</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <i class="fas fa-chart-pie me-2"></i>توزيع المستخدمين
                                </div>
                                <div class="card-body">
                                    <canvas id="usersChartModal" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <i class="fas fa-chart-line me-2"></i>المبيعات الشهرية
                                </div>
                                <div class="card-body">
                                    <canvas id="salesChartModal" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('styles')
    <style>
        .card {
            border-radius: 10px;
            border: none;
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .border-left-primary {
            border-left: 4px solid #4e73df;
        }

        .border-left-secondary {
            border-left: 4px solid #6c757d;
        }

        .border-left-success {
            border-left: 4px solid #1cc88a;
        }

        .border-left-info {
            border-left: 4px solid #36b9cc;
        }

        .border-left-warning {
            border-left: 4px solid #f6c23e;
        }

        .border-left-danger {
            border-left: 4px solid #e74a3b;
        }

        .border-left-dark {
            border-left: 4px solid #5a5c69;
        }

        .avatar-circle-sm {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .chart-placeholder {
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }

        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }

        .btn-group .btn {
            border-radius: 4px !important;
            margin: 0 2px;
        }
    </style>
@endsection

@section('scripts')
    <script>
        function showCharts() {
            $('#chartsModal').modal('show');
        }

        // دوال لعرض كل البيانات (يمكن تطويرها لاحقاً)
        function showAllUsers() {
            alert('يمكن تطوير هذه الوظيفة لعرض جميع المستخدمين في نافذة منبثقة أو صفحة منفصلة');
            // أو: window.location.href = '/users';
        }

        function showAllCategories() {
            alert('يمكن تطوير هذه الوظيفة لعرض جميع الفئات');
        }

        function showAllProducts() {
            alert('يمكن تطوير هذه الوظيفة لعرض جميع المنتجات');
        }

        function showAllCustomers() {
            alert('يمكن تطوير هذه الوظيفة لعرض جميع العملاء');
        }

        function showAllOrders() {
            alert('يمكن تطوير هذه الوظيفة لعرض جميع الطلبات');
        }

        function showAllEmployees() {
            alert('يمكن تطوير هذه الوظيفة لعرض جميع الموظفين');
        }

        function showAllSales() {
            alert('يمكن تطوير هذه الوظيفة لعرض جميع المبيعات');
        }

        // تهيئة الرسوم البيانية عند فتح المودال
        $('#chartsModal').on('shown.bs.modal', function() {
            // سيتم إضافة كود Charts.js هنا مستقبلاً
            console.log('الرسوم البيانية جاهزة للإضافة');
        });
    </script>
@endsection
