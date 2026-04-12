<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Ensure the authenticated user has verified their email (email_verified_at set).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('verification.verify')
                ->with('message', 'Please verify your email address to continue.');
        }

        if ($user->email_verified_at !== null) {
            return $next($request);
        }

        $pendingUserId = $user->getAuthIdentifier();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('pending_email_verification_user_id', $pendingUserId);

        return redirect()->route('verification.verify')
            ->with('message', 'Please verify your email address to continue.');
    }
}
