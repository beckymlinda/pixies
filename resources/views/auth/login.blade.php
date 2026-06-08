<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login | Pixies Bar Management</title>
    
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
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(30, 41, 59, 0.4) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        
        .login-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-header {
            background: #f8fafc;
            padding: 3rem 2rem 2rem;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .brand-logo {
            width: 64px;
            height: 64px;
            background: var(--navy-deep);
            color: white;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.3);
        }
        
        .brand-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            color: var(--navy-deep);
            margin-bottom: 0.5rem;
        }
        
        .login-body {
            padding: 2.5rem 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--slate-800);
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            border-color: var(--blue-accent);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .btn-login {
            background: var(--navy-deep);
            border: none;
            color: white;
            padding: 0.85rem;
            border-radius: 12px;
            font-weight: 700;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2);
        }
        
        .btn-login:hover {
            background: #1e293b;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.3);
        }
        
        .footer-links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .footer-links a {
            color: var(--slate-800);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .footer-links a:hover {
            color: var(--blue-accent);
        }
    </style>
</head>
<body>
    
    <div class="login-card">
        <div class="login-header">
            <div class="brand-logo">
                <i class="bi bi-pentagon-fill"></i>
            </div>
            <h3 class="brand-name">Pixies Bar</h3>
            <p class="text-muted small px-4">Enter your credentials to access the management dashboard.</p>
        </div>
        
        <div class="login-body">
            @if (session('status'))
                <div class="alert alert-success border-0 small rounded-3 mb-4">
                    {{ session('status') }}
                </div>
            @endif
            
            <form method="POST" action="{{ route('login') }}">
                @csrf
                
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="your@email.com" value="{{ old('email') }}" required autofocus>
                    @error('email')
                        <div class="text-danger small mt-1 fw-bold">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="mb-4">
                    <div class="d-flex justify-content-between">
                        <label class="form-label">Password</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="small text-decoration-none fw-bold" style="color: var(--blue-accent)">Forgot?</a>
                        @endif
                    </div>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                    @error('password')
                        <div class="text-danger small mt-1 fw-bold">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="mb-4">
                    <div class="form-check small">
                        <input class="form-check-input" type="checkbox" id="remember_me" name="remember">
                        <label class="form-check-label text-muted fw-medium" for="remember_me">
                            Keep me signed in for 30 days
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    Sign In to Dashboard
                </button>
                
                <div class="footer-links">
                    <p class="text-muted small">© {{ date('Y') }} Pixies Bar Management. v2.0</p>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
