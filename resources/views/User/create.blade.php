@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-user-plus me-2"></i>
                        {{ __('Language.CreateUser') }}
                    </div>
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-light">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="card-body p-4">
                    @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    @endif

                    <form method="POST" action="{{ route('user.store') }}" class="row g-3">
                        @csrf

                        <!-- ID Field -->
                        <div class="col-md-6">
                            <label for="id" class="form-label">
                                <i class="fas fa-id-card text-primary me-1"></i>
                                {{ __('Language.ID') }}
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   name="id"
                                   id="id"
                                   class="form-control @error('id') is-invalid @enderror"
                                   value="{{ old('id') }}"
                                   required>
                            @error('id')
                                <div class="invalid-feedback d-block">
                                    <i class="fas fa-exclamation-circle me-1"></i> {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Name Field -->
                        <div class="col-md-6">
                            <label for="name" class="form-label">
                                <i class="fas fa-user text-primary me-1"></i>
                                {{ __('Language.Name') }}
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   name="name"
                                   id="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}"
                                   required>
                            @error('name')
                                <div class="invalid-feedback d-block">
                                    <i class="fas fa-exclamation-circle me-1"></i> {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Email Field -->
                        <div class="col-md-6">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope text-primary me-1"></i>
                                {{ __('Language.Email') }}
                                <span class="text-danger">*</span>
                            </label>
                            <input type="email"
                                   name="email"
                                   id="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}"
                                   required>
                            @error('email')
                                <div class="invalid-feedback d-block">
                                    <i class="fas fa-exclamation-circle me-1"></i> {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Role Field -->
                        <div class="col-md-6">
                            <label for="role" class="form-label">
                                <i class="fas fa-user-tag text-primary me-1"></i>
                                {{ __('Language.Role') }}
                                <span class="text-danger">*</span>
                            </label>
                            <select name="role"
                                    id="role"
                                    class="form-select @error('role') is-invalid @enderror"
                                    required>
                                <option value="" selected disabled>اختر الدور</option>
                                <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>مدير</option>
                                <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>مستخدم</option>
                            </select>
                            @error('role')
                                <div class="invalid-feedback d-block">
                                    <i class="fas fa-exclamation-circle me-1"></i> {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Password Field -->
                        <div class="col-md-6">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock text-primary me-1"></i>
                                {{ __('Language.Password') }}
                                <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password"
                                       name="password"
                                       id="password"
                                       class="form-control @error('password') is-invalid @enderror"
                                       required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">
                                    <i class="fas fa-exclamation-circle me-1"></i> {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Confirm Password Field -->
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">
                                <i class="fas fa-lock text-primary me-1"></i>
                                تأكيد كلمة المرور
                                <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password"
                                       name="password_confirmation"
                                       id="password_confirmation"
                                       class="form-control"
                                       required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Status Field -->
                        <div class="col-md-6">
                            <label for="status" class="form-label">
                                <i class="fas fa-check-circle text-primary me-1"></i>
                                {{ __('Language.Status') }}
                                <span class="text-danger">*</span>
                            </label>
                            <select name="status"
                                    id="status"
                                    class="form-select @error('status') is-invalid @enderror"
                                    required>
                                <option value="" selected disabled>اختر الحالة</option>
                                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>نشط</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>غير نشط</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback d-block">
                                    <i class="fas fa-exclamation-circle me-1"></i> {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Profile Image Field -->
                        <div class="col-md-6">
                            <label for="image" class="form-label">
                                <i class="fas fa-image text-primary me-1"></i>
                                صورة الملف الشخصي
                            </label>
                            <input type="file"
                                   name="image"
                                   id="image"
                                   class="form-control @error('image') is-invalid @enderror"
                                   accept="image/*">
                            @error('image')
                                <div class="invalid-feedback d-block">
                                    <i class="fas fa-exclamation-circle me-1"></i> {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Actions -->
                        <div class="col-12 mt-4">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-right me-2"></i> رجوع
                                </a>
                                <div>
                                    <button type="reset" class="btn btn-outline-danger me-2">
                                        <i class="fas fa-redo me-2"></i> إعادة تعيين
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-user-plus me-2"></i> {{ __('Language.CreateUser') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card {
        border-radius: 10px;
        border: none;
    }

    .card-header {
        border-radius: 10px 10px 0 0 !important;
        font-weight: bold;
        font-size: 1.1rem;
    }

    .form-label {
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #ced4da;
        padding: 0.5rem 0.75rem;
        transition: all 0.3s;
    }

    .form-control:focus, .form-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .invalid-feedback {
        font-size: 0.875rem;
    }

    .btn {
        border-radius: 8px;
        padding: 0.5rem 1.5rem;
        font-weight: 500;
        transition: all 0.3s;
    }

    .btn-primary {
        background-color: #4e73df;
        border-color: #4e73df;
    }

    .btn-primary:hover {
        background-color: #2e59d9;
        border-color: #2e59d9;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(78, 115, 223, 0.2);
    }

    .btn-outline-secondary {
        border-color: #ddd;
    }

    .btn-outline-secondary:hover {
        background-color: #f8f9fa;
        border-color: #ccc;
    }

    .alert {
        border-radius: 8px;
        border: none;
    }

    .input-group {
        border-radius: 8px;
    }

    .input-group .btn-outline-secondary {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('password_confirmation');

        if (togglePassword && password) {
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }

        if (toggleConfirmPassword && confirmPassword) {
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPassword.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }

        // Form validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            // Check password match
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('كلمة المرور وتأكيدها غير متطابقين');
                return false;
            }

            return true;
        });
    });
</script>
@endpush
