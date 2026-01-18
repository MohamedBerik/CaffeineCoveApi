<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-4">
    @yield('content')
</div>

@if(session('success'))
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999">
    <div class="toast show text-bg-success">
        <div class="toast-body">
            {{ session('success') }}
        </div>
    </div>
</div>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.toast').forEach(t => new bootstrap.Toast(t).show());
});
</script>

</body>
</html>
