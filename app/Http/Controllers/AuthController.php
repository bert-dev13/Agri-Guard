<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use App\Services\AiRecommendationService;
use App\Services\WeatherAdvisoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /** Code validity in minutes. */
    private const VERIFICATION_CODE_EXPIRY_MINUTES = 5;

    /**
     * Generate a 6-digit numeric OTP and send verification email; store hashed code and expiry on user.
     */
    private function sendVerificationCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->email_verification_code = Hash::make($code);
        $user->email_verification_expires_at = now()->addMinutes(self::VERIFICATION_CODE_EXPIRY_MINUTES);
        $user->verification_attempts = 0;
        $user->verification_locked_until = null;
        $user->save();

        Mail::to($user->email)->send(new EmailVerificationCodeMail(
            $code,
            (string) self::VERIFICATION_CODE_EXPIRY_MINUTES
        ));

        if (config('mail.default') === 'log' && config('app.debug')) {
            session()->flash('dev_verification_code', $code);
        }
    }
    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle login request.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();
            if ($user->email_verified_at === null) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $request->session()->put('pending_email_verification_user_id', $user->id);
                $this->sendVerificationCode($user->fresh());
                return redirect()->route('verification.verify')
                    ->with('message', 'Please verify your email. A new code has been sent to your email address.');
            }
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Show the registration form.
     */
    public function showRegisterForm()
    {
        return view('auth.register');
    }

    /**
     * Handle registration request.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'farm_municipality' => ['required', 'string', 'in:Amulung'],
            'farm_barangay' => ['required', 'string', 'max:20'],
            'crop_type' => ['nullable', 'string', 'in:Rice,Corn'],
            'farming_stage' => ['nullable', 'string', 'in:land_preparation,planting,early_growth,growing,flowering_fruiting,harvesting'],
            'planting_date' => ['nullable', 'date'],
            'farm_area' => ['nullable', 'numeric', 'min:0.01'],
        ], [
            'name.required' => 'Please enter your name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Please enter a password.',
            'password.confirmed' => 'The password confirmation does not match.',
            'farm_municipality.required' => 'Farm municipality is required.',
            'farm_municipality.in' => 'Farm municipality must be Amulung.',
            'farm_barangay.required' => 'Please select your farm barangay.',
            'planting_date.date' => 'Please enter a valid planting date.',
            'farm_area.numeric' => 'Farm area must be a number (square meters).',
            'farm_area.min' => 'Farm area must be greater than 0.',
            'crop_type.in' => 'Crop type must be Rice or Corn.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'farm_municipality' => $validated['farm_municipality'],
            'farm_barangay' => $validated['farm_barangay'],
            'crop_type' => $validated['crop_type'] ?? null,
            'farming_stage' => $validated['farming_stage'] ?? null,
            'planting_date' => isset($validated['planting_date']) ? $validated['planting_date'] : null,
            'farm_area' => isset($validated['farm_area']) ? (float) $validated['farm_area'] : null,
        ]);

        $this->sendVerificationCode($user);
        $request->session()->put('pending_email_verification_user_id', $user->id);

        return redirect()->route('verification.verify')
            ->with('message', 'We sent a verification code to your email. Enter it below to activate your account.');
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    /**
     * Show the dashboard (authenticated).
     * Weather API failures are caught so the dashboard always renders with a fallback state.
     */
    public function dashboard(WeatherAdvisoryService $weatherAdvisory, AiRecommendationService $aiRecommendationService)
    {
        $user = Auth::user();

        try {
            $advisoryData = $weatherAdvisory->getAdvisoryData($user);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard: weather advisory failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            $advisoryData = [
                'weather' => null,
                'forecast' => [],
                'charts' => [
                    'monthly_trend' => [],
                    'yearly_totals' => [],
                    'heavy_rainfall' => ['by_year' => []],
                ],
                'location_display' => $user->farm_location_display,
                'forecast_rain_probability' => null,
                'rain_probability_display' => null,
                'weather_data' => [],
                'last_updated' => null,
            ];
        }

        $weather = $advisoryData['weather'] ?? [];
        $forecast = $advisoryData['forecast'] ?? [];
        $forecastRainProbability = $advisoryData['forecast_rain_probability'] ?? null;
        $todayRainProbability = $weather['today_rain_probability'] ?? $forecastRainProbability;
        $todayRainfallMm = $weather['today_expected_rainfall'] ?? null;
        $weekRainfallMm = is_numeric($todayRainfallMm) ? ((float) $todayRainfallMm * 7) : null;
        $monthRainfallMm = is_numeric($todayRainfallMm) ? ((float) $todayRainfallMm * 30) : null;

        $morningRain = isset($forecast[0]['pop']) ? (float) $forecast[0]['pop'] : null;
        $afternoonRain = isset($forecast[1]['pop']) ? (float) $forecast[1]['pop'] : $morningRain;
        $eveningRain = isset($forecast[2]['pop']) ? (float) $forecast[2]['pop'] : $afternoonRain;

        $smartRecommendation = $aiRecommendationService->generateSmartRecommendation($user, [
            'weather' => [
                'temperature' => $weather['temp'] ?? null,
                'humidity' => $weather['humidity'] ?? null,
                'wind_speed' => $weather['wind_speed'] ?? null,
                'condition' => $weather['condition']['main'] ?? ($weather['condition']['description'] ?? 'Unknown'),
                'rain_chance' => $todayRainProbability,
            ],
            'hourly_summary' => [
                'morning_rain_chance' => $morningRain,
                'afternoon_rain_chance' => $afternoonRain,
                'evening_rain_chance' => $eveningRain,
            ],
            'short_forecast' => array_map(static function (array $day): array {
                return [
                    'day' => (string) ($day['day_name'] ?? ''),
                    'condition' => (string) ($day['condition']['main'] ?? ($day['condition']['description'] ?? 'Unknown')),
                    'temp_min' => is_numeric($day['temp_min'] ?? null) ? (float) $day['temp_min'] : null,
                    'temp_max' => is_numeric($day['temp_max'] ?? null) ? (float) $day['temp_max'] : null,
                    'rain_chance' => is_numeric($day['pop'] ?? null) ? (int) round((float) $day['pop']) : null,
                    'wind_speed' => is_numeric($day['wind_speed'] ?? null) ? (float) $day['wind_speed'] : null,
                ];
            }, array_slice($forecast, 0, 3)),
            'rainfall_summary' => [
                'today_mm' => $todayRainfallMm,
                'week_mm' => $weekRainfallMm,
                'month_mm' => $monthRainfallMm,
                'trend' => is_numeric($todayRainProbability) && (float) $todayRainProbability >= 60 ? 'increasing' : 'stable',
            ],
            'system_flags' => [
                'flood_risk' => is_numeric($todayRainProbability) && (float) $todayRainProbability >= 75,
                'soil_saturation' => is_numeric($monthRainfallMm) && (float) $monthRainfallMm >= 220,
                'irrigation_needed' => is_numeric($todayRainProbability) && (float) $todayRainProbability < 40,
                'good_for_spraying' => is_numeric($todayRainProbability) && (float) $todayRainProbability < 35 && (float) ($weather['wind_speed'] ?? 0) < 20,
            ],
        ], 'dashboard');

        return view('user.dashboard', [
            'advisoryData' => $advisoryData,
            'recommendation' => $smartRecommendation['recommendation'],
            'recommendation_failed' => $smartRecommendation['failed'],
        ]);
    }

    /**
     * Show the verify email (OTP) form. Requires pending_email_verification_user_id in session.
     */
    public function showVerifyEmailForm(Request $request)
    {
        $userId = $request->session()->get('pending_email_verification_user_id');
        if (! $userId) {
            return redirect()->route('register')
                ->with('message', 'Please register first to verify your email.');
        }
        $user = User::find($userId);
        if (! $user || ! $user->hasPendingEmailVerification()) {
            $request->session()->forget('pending_email_verification_user_id');
            return redirect()->route('register')
                ->with('message', 'Your session expired. Please register again.');
        }
        return view('auth.verify-email', [
            'email' => $user->email,
            'lockedUntil' => $user->verification_locked_until,
            'devCode' => session('dev_verification_code'),
            'expiresAt' => $user->email_verification_expires_at,
        ]);
    }

    /**
     * Verify the 6-digit code. Rate limited to prevent brute force.
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ], [
            'code.required' => 'Please enter the verification code.',
            'code.size' => 'The verification code must be 6 digits.',
            'code.regex' => 'The verification code must contain only numbers.',
        ]);

        $userId = $request->session()->get('pending_email_verification_user_id');
        if (! $userId) {
            return redirect()->route('register')
                ->withErrors(['code' => 'Session expired. Please register again.']);
        }
        $user = User::find($userId);
        if (! $user || ! $user->hasPendingEmailVerification()) {
            $request->session()->forget('pending_email_verification_user_id');
            return redirect()->route('register')
                ->withErrors(['code' => 'Invalid session. Please register again.']);
        }
        if ($user->isVerificationLocked()) {
            return back()->withErrors([
                'code' => 'Too many failed attempts. You can try again after ' . $user->verification_locked_until->diffForHumans() . '.',
            ]);
        }
        if ($user->email_verification_expires_at && $user->email_verification_expires_at->isPast()) {
            return back()->withErrors(['code' => 'This code has expired. Please request a new code.']);
        }
        if (! Hash::check($request->input('code'), $user->email_verification_code)) {
            $user->verification_attempts = ($user->verification_attempts ?? 0) + 1;
            $maxAttempts = 5;
            if ($user->verification_attempts >= $maxAttempts) {
                $user->verification_locked_until = now()->addMinutes(15);
            }
            $user->save();
            $remaining = $maxAttempts - $user->verification_attempts;
            $message = 'The verification code is incorrect.';
            if ($remaining > 0 && $user->verification_locked_until === null) {
                $message .= ' ' . $remaining . ' attempt(s) remaining.';
            }
            return back()->withErrors(['code' => $message]);
        }
        $user->email_verified_at = now();
        $user->email_verification_code = null;
        $user->email_verification_expires_at = null;
        $user->verification_attempts = 0;
        $user->verification_locked_until = null;
        $user->save();
        $request->session()->forget('pending_email_verification_user_id');
        // Do not auto-login after verification; keep the user as a guest.
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Your email has been verified. Please log in.');
    }

    /**
     * Resend the verification code. Throttled (e.g. 1 per minute).
     */
    public function resendVerificationCode(Request $request)
    {
        $userId = $request->session()->get('pending_email_verification_user_id');
        if (! $userId) {
            return redirect()->route('register')
                ->withErrors(['resend' => 'Session expired. Please register again.']);
        }
        $user = User::find($userId);
        if (! $user || ! $user->hasPendingEmailVerification()) {
            $request->session()->forget('pending_email_verification_user_id');
            return redirect()->route('register')->withErrors(['resend' => 'Invalid session. Please register again.']);
        }
        if ($user->isVerificationLocked()) {
            return back()->withErrors([
                'resend' => 'You cannot request a new code until ' . $user->verification_locked_until->diffForHumans() . '.',
            ]);
        }
        $key = 'resend_verification_' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors([
                'resend' => 'Please wait a minute before requesting another code.',
            ]);
        }
        RateLimiter::hit($key, 60);
        $this->sendVerificationCode($user->fresh());
        return back()->with('message', 'A new verification code has been sent to your email.');
    }
}
