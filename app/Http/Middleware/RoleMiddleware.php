<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $role
     */
    public function handle(Request $request, Closure $next, string $role, string ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        $requiredRoles = $role;
        if (!empty($roles)) {
            $requiredRoles .= ',' . implode(',', $roles);
        }

        // Check if user has the required role(s)
        if (!$user->hasRole($requiredRoles)) {
            abort(403, 'Unauthorized access');
        }

        // Additional checks for sellers - they can only access their own bar
        if ($role === 'seller' && $user->bar_id) {
            // You can add bar-specific logic here if needed
        }

        return $next($request);
    }
}
