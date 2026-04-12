<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    private const CODE_EXPIRY_MINUTES = 10;

    private const RESET_SESSION_MINUTES = 15;

    private const MAX_VERIFY_ATTEMPTS = 5;

    private const LOCK_MINUTES_AFTER_MAX = 15;

    private const SESSION_USER_ID = 'password_reset_allowed_user_id';

    private const SESSION_EXPIRES_AT = 'password_reset_allowed_expires_at';

    /** Email submitted on forgot-password; used to pre-fill verify step and must match verify/resend posts. */
    private const SESSION_EMAIL = 'password_reset_email';

    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a reset code if the email belongs to a user. Always the same redirect for unknown emails.
     */
    public function sendCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $email = trim($validated['email']);
        $request->session()->put(self::SESSION_EMAIL, $email);

        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            try {
                $this->issueAndEmailResetCode($user);
            } catch (\Throwable $e) {
                Log::error('Password reset: failed to send mail', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);

                $request->session()->forget(self::SESSION_EMAIL);

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors([
                        'email' => 'We could not send the email right now. Please try again in a few minutes or contact support if the problem continues.',
                    ]);
            }
        }

        return redirect()
            ->route('password.verify.form')
            ->with('status', 'If the email is registered, a verification code has been sent.');
    }

    public function showVerifyForm(Request $request)
    {
        $sessionEmail = $request->session()->get(self::SESSION_EMAIL);
        if (! is_string($sessionEmail) || trim($sessionEmail) === '') {
            return redirect()
                ->route('password.request')
                ->with('message', 'Please enter your email first.');
        }

        $sessionEmail = trim($sessionEmail);
        $emailForLock = $request->old('email') ?: $sessionEmail;

        return view('auth.password-reset-verify', [
            'email' => old('email', $sessionEmail),
            'emailReadonly' => true,
            'lockedUntil' => $this->resolveLockedUntilForEmail(is_string($emailForLock) ? $emailForLock : null),
        ]);
    }

    /**
     * Re-evaluate lock banner when old email is repopulated after validation error.
     */
    private function resolveLockedUntilForEmail(?string $email): ?\Illuminate\Support\Carbon
    {
        if (! is_string($email) || $email === '') {
            return null;
        }
        $user = User::query()->where('email', $email)->first();
        if (! $user instanceof User || ! $user->password_reset_locked_until?->isFuture()) {
            return null;
        }

        return $user->password_reset_locked_until;
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'code.required' => 'Please enter the verification code.',
            'code.size' => 'The verification code must be 6 digits.',
            'code.regex' => 'The verification code must contain only numbers.',
        ]);

        $sessionEmail = $request->session()->get(self::SESSION_EMAIL);
        if (! is_string($sessionEmail) || $sessionEmail === '') {
            return redirect()
                ->route('password.request')
                ->with('message', 'Please enter your email first.');
        }

        if (! hash_equals(strtolower($sessionEmail), strtolower((string) $request->input('email')))) {
            return redirect()
                ->route('password.request')
                ->with('message', 'Please enter your email first.');
        }

        $user = User::query()->where('email', $request->input('email'))->first();

        if (! $user instanceof User || $user->password_reset_code === null) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['code' => 'Invalid or expired code.']);
        }

        if ($user->isPasswordResetLocked()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'code' => 'Too many failed attempts. You can try again '.$user->password_reset_locked_until->diffForHumans().'.',
                ]);
        }

        if ($user->password_reset_expires_at === null || $user->password_reset_expires_at->isPast()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['code' => 'Invalid or expired code.']);
        }

        if (! Hash::check($request->input('code'), $user->password_reset_code)) {
            $attempts = (int) $user->password_reset_attempts + 1;
            $lockedUntil = null;
            if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
                $lockedUntil = now()->addMinutes(self::LOCK_MINUTES_AFTER_MAX);
            }
            User::query()->whereKey($user->getKey())->update([
                'password_reset_attempts' => $attempts,
                'password_reset_locked_until' => $lockedUntil,
            ]);
            $remaining = self::MAX_VERIFY_ATTEMPTS - $attempts;
            $message = 'Invalid or expired code.';
            if ($remaining > 0 && $lockedUntil === null) {
                $message .= ' '.$remaining.' attempt(s) remaining.';
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['code' => $message]);
        }

        User::query()->whereKey($user->getKey())->update([
            'password_reset_code' => null,
            'password_reset_expires_at' => null,
            'password_reset_attempts' => 0,
            'password_reset_locked_until' => null,
        ]);

        $request->session()->put(self::SESSION_USER_ID, $user->id);
        $request->session()->put(
            self::SESSION_EXPIRES_AT,
            now()->addMinutes(self::RESET_SESSION_MINUTES)->getTimestamp()
        );

        return redirect()
            ->route('password.reset.form')
            ->with('message', 'Verification successful. Choose a new password below.');
    }

    public function resendCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $sessionEmail = $request->session()->get(self::SESSION_EMAIL);
        if (! is_string($sessionEmail) || $sessionEmail === '') {
            return redirect()
                ->route('password.request')
                ->with('message', 'Please enter your email first.');
        }

        if (! hash_equals(strtolower($sessionEmail), strtolower((string) $request->input('email')))) {
            return redirect()
                ->route('password.request')
                ->with('message', 'Please enter your email first.');
        }

        $user = User::query()->where('email', $request->input('email'))->first();

        if (! $user instanceof User) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'resend' => 'No active reset was found for this email. Enter the email on the forgot password page first.',
                ]);
        }

        $key = 'password_reset_resend_'.sha1(strtolower($user->email));
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['resend' => 'Please wait a minute before requesting another code.']);
        }
        RateLimiter::hit($key, 60);

        try {
            $this->issueAndEmailResetCode($user->fresh());
        } catch (\Throwable $e) {
            Log::error('Password reset resend: mail failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'resend' => 'We could not send the email right now. Please try again shortly.',
                ]);
        }

        return back()
            ->withInput($request->only('email'))
            ->with('message', 'Verification code sent successfully.');
    }

    public function showResetForm(Request $request)
    {
        if (! $this->resetSessionIsValid($request)) {
            return redirect()
                ->route('password.verify.form')
                ->withErrors(['email' => 'Please verify your code again before setting a new password.']);
        }

        $userId = $request->session()->get(self::SESSION_USER_ID);
        $user = User::query()->find($userId);

        if (! $this->ensurePasswordResetEmailInSession($request, $user)) {
            return redirect()
                ->route('password.request')
                ->with('message', 'Please enter your email first.');
        }

        return view('auth.password-reset-form', [
            'email' => $user?->email ?? '',
        ]);
    }

    public function resetPassword(Request $request)
    {
        if (! $this->resetSessionIsValid($request)) {
            return redirect()
                ->route('password.verify.form')
                ->withErrors(['email' => 'Please verify your code again before setting a new password.']);
        }

        $userId = $request->session()->get(self::SESSION_USER_ID);
        $user = User::query()->find($userId);
        if (! $this->ensurePasswordResetEmailInSession($request, $user)) {
            return redirect()
                ->route('password.request')
                ->with('message', 'Please enter your email first.');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'password.required' => 'Please enter a new password.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        if (! $user instanceof User) {
            $this->forgetResetSession($request);

            return redirect()->route('password.verify.form');
        }

        $user->password = $validated['password'];
        $user->save();

        User::query()->whereKey($user->getKey())->update([
            'password_reset_code' => null,
            'password_reset_expires_at' => null,
            'password_reset_attempts' => 0,
            'password_reset_locked_until' => null,
        ]);

        $this->forgetResetSession($request);
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', 'Password reset successfully. You can now log in with your new password.');
    }

    private function resetSessionIsValid(Request $request): bool
    {
        $userId = $request->session()->get(self::SESSION_USER_ID);
        $expiresAt = $request->session()->get(self::SESSION_EXPIRES_AT);
        if (! $userId || ! is_int($expiresAt)) {
            return false;
        }

        return $expiresAt > now()->getTimestamp();
    }

    private function forgetResetSession(Request $request): void
    {
        $request->session()->forget([self::SESSION_USER_ID, self::SESSION_EXPIRES_AT, self::SESSION_EMAIL]);
    }

    /**
     * Keep session email aligned with the account being reset when the reset session is valid.
     */
    private function ensurePasswordResetEmailInSession(Request $request, ?User $user): bool
    {
        $sessionEmail = $request->session()->get(self::SESSION_EMAIL);
        if (is_string($sessionEmail) && trim($sessionEmail) !== '') {
            return true;
        }
        if ($user instanceof User && $user->email !== '') {
            $request->session()->put(self::SESSION_EMAIL, $user->email);

            return true;
        }

        return false;
    }

    /**
     * Store hashed code, expiry, reset counters; send plain code by email. Rolls back DB on mail failure.
     */
    private function issueAndEmailResetCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hashed = Hash::make($code);
        $expiresAt = now()->addMinutes(self::CODE_EXPIRY_MINUTES);

        User::query()->whereKey($user->getKey())->update([
            'password_reset_code' => $hashed,
            'password_reset_expires_at' => $expiresAt,
            'password_reset_attempts' => 0,
            'password_reset_locked_until' => null,
        ]);

        try {
            Mail::to($user->email)->send(new PasswordResetCodeMail(
                $code,
                (string) self::CODE_EXPIRY_MINUTES
            ));
        } catch (\Throwable $e) {
            User::query()->whereKey($user->getKey())->update([
                'password_reset_code' => null,
                'password_reset_expires_at' => null,
                'password_reset_attempts' => 0,
            ]);
            throw $e;
        }

        if (config('mail.default') === 'log' && config('app.debug')) {
            session()->flash('dev_password_reset_code', $code);
        }
    }
}
