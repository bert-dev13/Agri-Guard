<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CropProgressController;
use App\Http\Controllers\PsgcController;
use App\Http\Controllers\RainfallTrendsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\WeatherDetailsController;
use Illuminate\Support\Facades\Route;

// Landing page: show to guests; redirect authenticated users to dashboard
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('public.landing');
})->name('landing');

// Guest-only routes (redirect to dashboard if already logged in)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    // Email verification (OTP) – requires pending_email_verification_user_id in session
    Route::get('/verify-email', [AuthController::class, 'showVerifyEmailForm'])->name('verification.verify');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:10,1')->name('verification.submit');
    Route::post('/resend-verification-code', [AuthController::class, 'resendVerificationCode'])->middleware('throttle:3,1')->name('verification.resend');
});

// PSGC API proxy – Amulung barangays (for registration page)
Route::get('/api/amulung-barangays', [PsgcController::class, 'amulungBarangays'])->name('api.amulung-barangays');

// Protected routes: unauthenticated users are redirected to /login (auth middleware).
// Email must be verified to access dashboard (verified.email middleware).
Route::middleware(['auth', 'verified.email'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    Route::get('/api/weather', [WeatherController::class, 'index'])->name('api.weather');
    Route::get('/api/weather/by-coordinates', [WeatherController::class, 'byCoordinates'])->name('api.weather.by-coordinates');
    Route::get('/weather', [WeatherDetailsController::class, 'show'])->name('weather-details');
    Route::get('/weather/rainfall', [RainfallTrendsController::class, 'show'])->name('rainfall-trends');
    Route::get('/crop-progress', [CropProgressController::class, 'index'])->name('crop-progress.index');
    Route::put('/crop-progress/stage', [CropProgressController::class, 'updateStage'])->name('crop-progress.update-stage');
    Route::redirect('/weather-details', '/weather', 301);
    Route::redirect('/rainfall-trends', '/weather/rainfall', 301);

    // Settings (account + farm profile)
    Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
    Route::put('/settings/account', [SettingsController::class, 'updateAccount'])->name('settings.account.update');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');
    Route::put('/settings/farm', [SettingsController::class, 'updateFarm'])->name('settings.farm.update');

    // Redirect legacy farm-profile URL to settings
    Route::get('/farm-profile', fn () => redirect()->route('settings'))->name('farm-profile');
});

// Forgot password placeholder (for future implementation)
Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('password.request')->middleware('guest');
