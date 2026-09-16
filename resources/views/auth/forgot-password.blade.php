<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password | Pixies Bar Management</title>
    <link rel="icon" type="image/png" href="{{ asset('images/fav.png') }}">

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
            padding: 1rem;
        }
        
        .reset-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .reset-header {
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
        }
        
        .brand-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            color: var(--navy-deep);
            margin-bottom: 0.5rem;
        }
        
        .reset-body {
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
        
        .btn-reset {
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
        
        .btn-reset:hover {
            background: #1e293b;
            transform: translateY(-1px);
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
    
    <div class="reset-card">
        <div class="reset-header">
            <div class="brand-logo">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h4 class="brand-name">Password Reset</h4>
            <p class="text-muted small">Forgot your password? No problem. Enter your email and we'll send you a reset link.</p>
        </div>
        
        <div class="reset-body">
            @if (session('status'))
                <div class="alert alert-success border-0 small rounded-3 mb-4">
                    {{ session('status') }}
                </div>
            @endif
            
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                
                <div class="mb-4">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="your@email.com" value="{{ old('email') }}" required autofocus>
                    @error('email')
                        <div class="text-danger small mt-1 fw-bold">{{ $message }}</div>
                    @enderror
                </div>
                
                <button type="submit" class="btn-reset">
                    Send Reset Link
                </button>
                
                <div class="footer-link">
                    <p class="text-muted">Remembered? <a href="{{ route('login') }}">Back to Login</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
