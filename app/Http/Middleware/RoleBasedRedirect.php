<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleBasedRedirect
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If user is authenticated, redirect to appropriate dashboard
        if (auth()->check()) {
            $user = auth()->user();
            
            // Redirect based on user role
            switch ($user->role) {
                case 'seller':
                    return redirect()->route('seller.dashboard');
                case 'manager':
                    return redirect()->route('manager.dashboard');
                case 'director':
                    return redirect()->route('director.dashboard');
                default:
                    return redirect()->route('dashboard');
            }
        }
        
        return $next($request);
    }
}
