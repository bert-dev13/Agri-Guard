<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Ensure the authenticated user has verified their email (email_verified_at set).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->email_verified_at === null) {
            if ($request->user()) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $request->session()->put('pending_email_verification_user_id', $request->user()->id);
            }
            return redirect()->route('verification.verify')
                ->with('message', 'Please verify your email address to continue.');
        }

        return $next($request);
    }
}
