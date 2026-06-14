<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Pending | Pixies Bar Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            color: #1e293b;
            padding: 2rem 1rem;
        }
        .pending-card {
            background: white;
            border-radius: 20px;
            max-width: 480px;
            width: 100%;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body>
    <div class="pending-card">
        <div class="text-warning display-4 mb-3"><i class="bi bi-hourglass-split"></i></div>
        <h1 class="h4 fw-bold mb-2">Account Pending</h1>
        <p class="text-muted mb-4">
            Hi {{ auth()->user()->name }}, your account has been created but no role has been assigned yet.
            Please contact an administrator to set your role in the system.
        </p>
        <p class="small text-muted mb-4">Valid roles: <code>seller</code>, <code>manager</code>, or <code>director</code></p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-dark rounded-pill px-4">Sign Out</button>
        </form>
    </div>
</body>
</html>
