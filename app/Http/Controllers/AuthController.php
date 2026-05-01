<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerificationCodeMail;
use App\Models\Barangay;
use App\Models\User;
use App\Services\AiRecommendationService;
use App\Services\CropTimelineService;
use App\Services\DashboardDisasterSummaryService;
use App\Services\ThreeDayWeatherOutlookService;
use App\Services\WeatherAdvisoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /** Code validity in minutes. */
    private const VERIFICATION_CODE_EXPIRY_MINUTES = 5;

    /** Target municipality for farmer registration (single-municipality deployment). */
    private const DEFAULT_FARM_MUNICIPALITY = 'Amulung';

    /**
     * Generate a 6-digit OTP, hash it, and persist expiry + attempt state on the user row.
     *
     * @return string Plain-text code (only for sending by mail in the same request).
     */
    private function generateAndStoreVerificationCode(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hashed = Hash::make($code);
        $expiresAt = now()->addMinutes(self::VERIFICATION_CODE_EXPIRY_MINUTES);

        // Persist via query builder so OTP state is always written regardless of fillable / model state.
        $updated = User::query()->whereKey($user->getKey())->update([
            'email_verification_code' => $hashed,
            'email_verification_expires_at' => $expiresAt,
            'verification_attempts' => 0,
            'verification_locked_until' => null,
        ]);

        if ($updated === 0) {
            Log::error('generateAndStoreVerificationCode: user row not updated', [
                'user_id' => $user->getKey(),
            ]);
            throw new \RuntimeException('Could not store email verification data.');
        }

        $user->refresh();

        return $code;
    }

    /**
     * Send the verification email. Failures are logged; callers decide whether to surface an error.
     */
    private function deliverVerificationCodeMail(User $user, string $plainCode): bool
    {
        try {
            Mail::to($user->email)->send(new EmailVerificationCodeMail(
                $plainCode,
                (string) self::VERIFICATION_CODE_EXPIRY_MINUTES
            ));

            return true;
        } catch (\Throwable $e) {
            $mailContext = [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'scheme' => config('mail.mailers.smtp.scheme'),
                'username' => config('mail.mailers.smtp.username'),
                'from_address' => config('mail.from.address'),
            ];

            Log::error('Email verification mail delivery failed', [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'mail_config' => $mailContext,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'previous_exception' => $e->getPrevious()?->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            report($e);

            return false;
        }
    }

    private function flashDevVerificationCodeIfApplicable(string $plainCode): void
    {
        // Intentionally left blank: never expose OTP in UI.
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
            if (! $user instanceof User) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'The provided credentials do not match our records.',
                ])->onlyInput('email');
            }

            if ($user->email_verified_at === null) {
                $pendingUserId = $user->id;
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $request->session()->put('pending_email_verification_user_id', $pendingUserId);

                $fresh = User::query()->find($pendingUserId);
                if (! $fresh instanceof User) {
                    Log::error('Login unverified: user row missing after session reset', [
                        'user_id' => $pendingUserId,
                    ]);

                    return redirect()->route('register')
                        ->with('message', 'Your account could not be loaded. Please register again.');
                }

                $plain = $this->generateAndStoreVerificationCode($fresh);
                $mailOk = $this->deliverVerificationCodeMail($fresh, $plain);
                $this->flashDevVerificationCodeIfApplicable($plain);

                return redirect()->route('verification.verify')
                    ->with(
                        'message',
                        $mailOk
                            ? 'Please verify your email. A new code has been sent to your email address.'
                            : 'Please verify your email. We could not send a new code just now — use “Resend code” on this page or try again shortly.'
                    );
            }
            $request->session()->regenerate();
            $home = $user->isAdmin() ? route('admin.dashboard') : route('dashboard');

            return redirect()->intended($home);
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
        $request->merge([
            'farm_municipality' => self::DEFAULT_FARM_MUNICIPALITY,
            'farm_barangay_code' => is_string($request->input('farm_barangay_code'))
                ? trim($request->input('farm_barangay_code'))
                : $request->input('farm_barangay_code'),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            ...(Barangay::query()->exists() ? [
                'farm_barangay_code' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::exists('barangays', 'id')->where('municipality', self::DEFAULT_FARM_MUNICIPALITY),
                ],
            ] : [
                'farm_barangay_code' => ['required', 'string', 'max:20'],
            ]),
            'crop_type' => ['required', 'string', 'in:Rice,Corn'],
            'planting_date' => [
                'required',
                'date',
                'before_or_equal:today',
                'after_or_equal:'.now()->subYears(5)->toDateString(),
            ],
            'farm_area' => ['required', 'numeric', 'min:0.01'],
        ], [
            'name.required' => 'Please enter your name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Please enter a password.',
            'password.confirmed' => 'The password confirmation does not match.',
            'farm_barangay_code.required' => 'Please select your farm barangay.',
            'farm_barangay_code.exists' => 'Please select a valid barangay for the chosen municipality.',
            'crop_type.required' => 'Please select your crop type.',
            'crop_type.in' => 'Crop type must be Rice or Corn.',
            'planting_date.required' => 'Please enter your planting date.',
            'planting_date.date' => 'Please enter a valid planting date.',
            'planting_date.before_or_equal' => 'Planting date cannot be in the future.',
            'planting_date.after_or_equal' => 'Planting date is too far in the past.',
            'farm_area.required' => 'Please enter your farm area (square meters).',
            'farm_area.numeric' => 'Farm area must be a number (square meters).',
            'farm_area.min' => 'Farm area must be greater than 0.',
        ]);

        $barangayName = Barangay::nameForId($validated['farm_barangay_code']);

        $timelineService = app(CropTimelineService::class);
        $preUser = new User;
        $preUser->forceFill([
            'crop_type' => $validated['crop_type'],
            'planting_date' => $validated['planting_date'],
        ]);
        $derivedStageKey = $timelineService->inferExpectedStageFromPlanting($preUser)['key'];

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'farmer',
            'farm_municipality' => self::DEFAULT_FARM_MUNICIPALITY,
            'farm_barangay' => $barangayName ?? '',
            'farm_barangay_code' => $validated['farm_barangay_code'],
            'crop_type' => $validated['crop_type'],
            'farming_stage' => $derivedStageKey,
            'planting_date' => $validated['planting_date'],
            'farm_area' => (float) $validated['farm_area'],
            'crop_timeline_offset_days' => 0,
            'reality_check_answered' => false,
        ]);

        // Persist OTP first, bind session, then send mail. On Render, SMTP timeouts or bad credentials
        // must not prevent the redirect — otherwise the user sees a blank 500 and the OTP flow breaks.
        $plain = $this->generateAndStoreVerificationCode($user);
        $request->session()->put('pending_email_verification_user_id', $user->id);

        $mailOk = $this->deliverVerificationCodeMail($user, $plain);
        $this->flashDevVerificationCodeIfApplicable($plain);

        if (! $mailOk) {
            Log::warning('Registration: user created but verification email was not delivered', [
                'user_id' => $user->id,
                'mailer' => config('mail.default'),
            ]);
        }

        return redirect()->route('verification.verify')
            ->with(
                'message',
                $mailOk
                    ? 'We sent a verification code to your email. Enter it below to activate your account.'
                    : 'Your account was created, but we could not send the verification code email right now. Please click "Resend code" below. If this continues, contact support.'
            );
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
    public function dashboard(
        WeatherAdvisoryService $weatherAdvisory,
        AiRecommendationService $aiRecommendationService,
        DashboardDisasterSummaryService $dashboardSummaryService,
        ThreeDayWeatherOutlookService $threeDayOutlook
    )
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

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

        $hourlyRows = array_slice(array_values($advisoryData['hourly_forecast'] ?? []), 0, 8);
        $hourlyForAi = array_map(static function (array $h): array {
            return [
                'time_local' => (string) ($h['time'] ?? ''),
                'rain_chance_pct' => isset($h['pop']) && is_numeric($h['pop']) ? (int) round((float) $h['pop']) : null,
                'temp_c' => isset($h['temp']) && is_numeric($h['temp']) ? (int) round((float) $h['temp']) : null,
            ];
        }, $hourlyRows);

        $bucketAvg = static function (array $rows, int $start, int $len): ?float {
            $slice = array_slice($rows, $start, $len);
            $pops = [];
            foreach ($slice as $row) {
                if (isset($row['pop']) && is_numeric($row['pop'])) {
                    $pops[] = (float) $row['pop'];
                }
            }
            if ($pops === []) {
                return null;
            }

            return round(array_sum($pops) / count($pops), 1);
        };
        $morningRain = $bucketAvg($hourlyRows, 0, 3);
        $afternoonRain = $bucketAvg($hourlyRows, 3, 3);
        $eveningRain = $bucketAvg($hourlyRows, 6, 2);

        $forecastNextDays = array_map(static function (array $day): array {
            return [
                'day_name' => (string) ($day['day_name'] ?? ''),
                'date' => (string) ($day['date'] ?? ''),
                'condition' => (string) ($day['condition']['main'] ?? ($day['condition']['description'] ?? '')),
                'temp_min_c' => is_numeric($day['temp_min'] ?? null) ? round((float) $day['temp_min'], 1) : null,
                'temp_max_c' => is_numeric($day['temp_max'] ?? null) ? round((float) $day['temp_max'], 1) : null,
                'rain_chance_pct' => is_numeric($day['pop'] ?? null) ? (int) round((float) $day['pop']) : null,
                'expected_rain_mm' => is_numeric($day['rain_mm'] ?? null) ? round((float) $day['rain_mm'], 1) : null,
            ];
        }, array_slice($forecast, 0, 5));

        $popsFive = array_filter(array_column(array_slice($forecast, 0, 5), 'pop'), static fn ($v) => is_numeric($v));
        $maxPopFive = $popsFive !== [] ? (int) round((float) max($popsFive)) : null;

        $cropTimeline = app(CropTimelineService::class);
        $calendarStageLabel = $cropTimeline->inferExpectedStageFromPlanting(
            $user,
            $cropTimeline->stageDurationsForCrop((string) ($user->crop_type ?? ''))
        )['label'];

        $smartRecommendation = $aiRecommendationService->generateSmartRecommendation($user, [
            'barangay' => trim((string) ($user->farm_barangay_name ?? '')),
            'farming_stage_label' => $calendarStageLabel,
            'forecast_next_days' => $forecastNextDays,
            'hourly_next_slots' => $hourlyForAi,
            'weather' => [
                'temperature' => $weather['temp'] ?? null,
                'humidity' => $weather['humidity'] ?? null,
                'wind_speed' => $weather['wind_speed'] ?? null,
                'condition' => $weather['condition']['main'] ?? ($weather['condition']['description'] ?? 'Unknown'),
                'rain_chance' => $todayRainProbability,
                'today_expected_rainfall_mm' => $todayRainfallMm,
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
                'max_rain_chance_next_5_days_pct' => $maxPopFive,
            ],
        ], 'dashboard');

        $disasterSummary = $dashboardSummaryService->build($user, $advisoryData);

        $weatherOutlook = $threeDayOutlook->build(
            ! empty($weather) ? $weather : null,
            $forecast
        );

        return view('user.dashboard', [
            'advisoryData' => $advisoryData,
            'recommendation' => $smartRecommendation['recommendation'],
            'recommendation_failed' => $smartRecommendation['failed'],
            'disasterSummary' => $disasterSummary,
            'weather_outlook' => $weatherOutlook,
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
                'code' => 'Too many failed attempts. You can try again after '.$user->verification_locked_until->diffForHumans().'.',
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
                $message .= ' '.$remaining.' attempt(s) remaining.';
            }

            return back()->withErrors(['code' => $message]);
        }

        // Clear OTP columns and set email_verified_at in one SQL update (source of truth for "verified").
        // OTP fields are *supposed* to be NULL after success—only email_verified_at proves verification.
        $verifiedAt = now();
        $updated = User::query()->whereKey($user->getKey())->update([
            'email_verified_at' => $verifiedAt,
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'verification_attempts' => 0,
            'verification_locked_until' => null,
        ]);

        if ($updated === 0) {
            Log::error('verifyEmail: could not persist verified state', ['user_id' => $user->getKey()]);

            return back()->withErrors([
                'code' => 'We could not save your verification. Please try again.',
            ]);
        }

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
                'resend' => 'You cannot request a new code until '.$user->verification_locked_until->diffForHumans().'.',
            ]);
        }
        $key = 'resend_verification_'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors([
                'resend' => 'Please wait a minute before requesting another code.',
            ]);
        }
        RateLimiter::hit($key, 60);

        $user = $user->fresh();
        $plain = $this->generateAndStoreVerificationCode($user);
        if (! $this->deliverVerificationCodeMail($user, $plain)) {
            return back()->withErrors([
                'resend' => 'We could not send the verification email at the moment. Please wait a minute and try again.',
            ]);
        }
        $this->flashDevVerificationCodeIfApplicable($plain);

        return back()->with('message', 'A new verification code has been sent to your email.');
    }
}
