<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $this->forgetIntendedUrlIfNotAllowedForRole($request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * A URL gets stashed as "intended" whenever an unauthenticated visit to a
     * protected route bounces to login - it has no idea which role is about
     * to sign in. Honoring it blindly can send a freshly logged-in user
     * (e.g. a seller, after a director's stale /stock link got stashed while
     * logged out) straight into RoleMiddleware's 403 right after a
     * successful login, which looks like login itself is broken. Drop the
     * stashed URL unless the now-authenticated user's role is actually
     * allowed on it, so redirect()->intended() falls back to the
     * role-appropriate dashboard instead.
     */
    private function forgetIntendedUrlIfNotAllowedForRole(Request $request): void
    {
        $intendedUrl = $request->session()->get('url.intended');

        if (! $intendedUrl) {
            return;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($intendedUrl, 'GET'));
        } catch (\Throwable $e) {
            // No matching GET route for the stashed URL - nothing
            // role-specific to check, leave it for intended() to handle.
            return;
        }

        $roleMiddleware = collect($route->gatherMiddleware())
            ->first(fn ($middleware) => str_starts_with($middleware, 'role:'));

        if (! $roleMiddleware) {
            return;
        }

        $allowedRoles = substr($roleMiddleware, strlen('role:'));

        if (! Auth::user()->hasRole($allowedRoles)) {
            $request->session()->forget('url.intended');
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
