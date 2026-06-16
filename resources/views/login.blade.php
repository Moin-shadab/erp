<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - MS ERP</title>

    <!-- Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .login-card {
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03), 0 5px 10px rgba(0, 0, 0, 0.01);
            border: 1px solid #e2e8f0;
            width: 100%;
            max-width: 440px;
            padding: 2.5rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 0.6rem 0.75rem;
            font-size: 0.85rem;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #2563eb;
        }

        .btn-primary {
            background-color: #2563eb;
            border: none;
            border-radius: 8px;
            padding: 0.6rem;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
        }

        .demo-btn {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.5rem;
            transition: all 0.2s;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 0.5rem;
        }

        .demo-btn:hover {
            background-color: #e2e8f0;
            color: #0f172a;
            border-color: #cbd5e1;
        }

        .demo-btn i {
            font-size: 1rem;
            color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <i class="bi bi-cpu text-primary fs-1 mb-2"></i>
            <h4 class="fw-bold mb-1">MS ERP Systems</h4>
            <p class="text-muted small">Sign in to access your enterprise workspace</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger small py-2 mb-3">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="/login" id="login-form">
            @csrf
            <!-- Email -->
            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-muted">Corporate Email</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="name@company.com">
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label for="password" class="form-label small fw-semibold text-muted">Password</label>
                <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
            </div>

            <!-- Submit -->
            <button type="submit" class="btn btn-primary w-100 mb-4">Sign In</button>
        </form>

        <!-- One-Click Demo Logs Section -->
        <div class="border-top pt-4">
            <h6 class="fw-bold small text-muted mb-3">ONE-CLICK DEMO SIGN-IN</h6>
            
            <button type="button" class="demo-btn" onclick="fillAndSubmit('admin@mserp.com', 'password')">
                <span><i class="bi bi-shield-check me-2"></i> CFO (Super Admin)</span>
                <i class="bi bi-arrow-right-short"></i>
            </button>
            
            <button type="button" class="demo-btn" onclick="fillAndSubmit('manager@mserp.com', 'password')">
                <span><i class="bi bi-people me-2"></i> Sales Lead (Manager)</span>
                <i class="bi bi-arrow-right-short"></i>
            </button>
            
            <button type="button" class="demo-btn" onclick="fillAndSubmit('user@mserp.com', 'password')">
                <span><i class="bi bi-person me-2"></i> Junior Clerk (User)</span>
                <i class="bi bi-arrow-right-short"></i>
            </button>
        </div>
    </div>

    <script>
        function fillAndSubmit(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            document.getElementById('login-form').submit();
        }
    </script>
</body>
</html>
