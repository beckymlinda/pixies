<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Pixies Bar Management</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
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
            color: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0;
            text-align: center;
        }
        
        .brand-logo {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--blue-accent);
            animation: fadeIn 1s ease-out;
        }
        
        .hero-title {
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }
        
        .hero-subtitle {
            font-size: 1.1rem;
            color: #94a3b8;
            max-width: 500px;
            margin-bottom: 3rem;
            line-height: 1.6;
        }
        
        .cta-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .btn-main {
            padding: 0.8rem 2rem;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-primary-custom {
            background: var(--blue-accent);
            color: white;
        }
        
        .btn-primary-custom:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        .btn-outline-custom {
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .btn-outline-custom:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: white;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .footer {
            position: absolute;
            bottom: 2rem;
            font-size: 0.8rem;
            color: #475569;
        }
    </style>
</head>
<body>
    
    <div class="brand-logo">
        <i class="bi bi-pentagon-fill"></i>
    </div>

    <h1 class="hero-title">Pixies Bar</h1>
    <p class="hero-subtitle">
        Intelligent management for modern hospitality operations.
        Stock, cash, and performance in one place.
    </p>

    <div class="cta-group">
        @auth
            <a href="{{ route('dashboard') }}" class="btn-main btn-primary-custom">Go to Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="btn-main btn-primary-custom">Sign In</a>
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn-main btn-outline-custom">Register</a>
            @endif
        @endauth
    </div>

    <div class="footer">
        © {{ date('Y') }} Pixies Bar Management. Built for Precision.
    </div>

</body>
</html>
