<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create Account | Pixies Bar Management</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --navy-deep: #0f172a;
            --slate-800: #1e293b;
            --blue-accent: #3b82f6;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--navy-deep);
            background-image: 
                radial-gradient(at 100% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(30, 41, 59, 0.4) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 2rem 1rem;
        }
        
        .register-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .register-header {
            background: #f8fafc;
            padding: 2.5rem 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .brand-logo {
            width: 56px;
            height: 56px;
            background: var(--navy-deep);
            color: white;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }
        
        .brand-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            color: var(--navy-deep);
            margin-bottom: 0.25rem;
        }
        
        .register-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--slate-800);
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.65rem 1rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            border-color: var(--blue-accent);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .btn-register {
            background: var(--navy-deep);
            border: none;
            color: white;
            padding: 0.85rem;
            border-radius: 12px;
            font-weight: 700;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.2s;
        }
        
        .btn-register:hover {
            background: #1e293b;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.3);
        }
        
        .footer-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
        }
        
        .footer-link a {
            color: var(--blue-accent);
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>
<body>
    
    <div class="register-card">
        <div class="register-header">
            <div class="brand-logo">
                <i class="bi bi-person-plus-fill"></i>
            </div>
            <h4 class="brand-name">Join Pixies Bar</h4>
            <p class="text-muted small">Create your account. An administrator will assign your role.</p>
        </div>
        
        <div class="register-body">
            <form method="POST" action="{{ route('register') }}">
                @csrf
                
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="John Doe" value="{{ old('name') }}" required autofocus autocomplete="name">
                    @error('name')
                        <div class="text-danger small mt-1 fw-bold">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="john@example.com" value="{{ old('email') }}" required autocomplete="username">
                    @error('email')
                        <div class="text-danger small mt-1 fw-bold">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="new-password">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Confirm</label>
                        <input type="password" name="password_confirmation" class="form-control" placeholder="••••••••" required autocomplete="new-password">
                    </div>
                    @error('password')
                        <div class="col-12">
                            <div class="text-danger small mt-1 fw-bold">{{ $message }}</div>
                        </div>
                    @enderror
                </div>
                
                <div class="mb-4">
                    <div class="form-check small">
                        <input class="form-check-input" type="checkbox" id="terms" required>
                        <label class="form-check-label text-muted fw-medium" for="terms">
                            I agree to the <a href="#" class="text-decoration-none">Terms of Service</a> and <a href="#" class="text-decoration-none">Privacy Policy</a>
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn-register">
                    Create Account
                </button>
                
                <div class="footer-link">
                    <p class="text-muted">Already have an account? <a href="{{ route('login') }}">Sign In</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
