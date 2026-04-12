<?php

use App\Http\Controllers\Admin\AccountSettingsController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FarmMonitoringController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AiFarmChatController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarangayApiController;
use App\Http\Controllers\CropProgressController;
use App\Http\Controllers\FarmMapController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\RainfallTrendsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\WeatherDetailsController;
use Illuminate\Support\Facades\Route;

// Landing page: show to guests; redirect authenticated users to dashboard
Route::get('/', LandingController::class)->name('landing');

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

    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])
        ->middleware('throttle:5,1')
        ->name('password.send');
    Route::get('/reset-password/verify', [PasswordResetController::class, 'showVerifyForm'])->name('password.verify.form');
    Route::post('/reset-password/verify', [PasswordResetController::class, 'verifyCode'])
        ->middleware('throttle:10,1')
        ->name('password.verify');
    Route::post('/reset-password/resend', [PasswordResetController::class, 'resendCode'])
        ->middleware('throttle:3,1')
        ->name('password.verify.resend');
    Route::get('/reset-password', [PasswordResetController::class, 'showResetForm'])->name('password.reset.form');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.reset');
});

// Barangay JSON for forms (public: registration uses it while guest)
Route::get('/api/barangays', [BarangayApiController::class, 'index'])->name('api.barangays');
Route::get('/api/amulung-barangays', [BarangayApiController::class, 'amulungBarangays'])->name('api.amulung-barangays');

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
    Route::post('/crop-progress/reality-check', [CropProgressController::class, 'realityCheck'])->name('crop-progress.reality-check');
    Route::post('/crop-progress/reality-check/reopen', [CropProgressController::class, 'reopenRealityCheck'])->name('crop-progress.reality-check-reopen');
    Route::put('/crop-progress/current-stage', [CropProgressController::class, 'updateCurrentStage'])->name('crop-progress.update-current-stage');
    Route::put('/crop-progress/stage', [CropProgressController::class, 'updateStage'])->name('crop-progress.update-stage');

    Route::get('/map', [FarmMapController::class, 'index'])->name('map.index');
    Route::post('/api/map/save-gps-location', [FarmMapController::class, 'saveGpsLocation'])->name('map.save-gps');
    Route::get('/api/map/farm-context', [FarmMapController::class, 'farmContext'])->name('map.farm-context');
    Route::get('/assistant', [AiFarmChatController::class, 'show'])->name('assistant.index');
    Route::post('/api/assistant/chat', [AiFarmChatController::class, 'chat'])->name('assistant.chat');
    Route::post('/api/assistant/clear', [AiFarmChatController::class, 'clear'])->name('assistant.clear');
    Route::redirect('/weather-details', '/weather', 301);
    Route::redirect('/rainfall-trends', '/weather/rainfall', 301);

    // Settings (account + farm profile)
    Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
    Route::put('/settings/account', [SettingsController::class, 'updateAccount'])->name('settings.account.update');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');
    Route::put('/settings/farm', [SettingsController::class, 'updateFarm'])->name('settings.farm.update');

    // Redirect legacy farm-profile URL to settings
    Route::permanentRedirect('/farm-profile', '/settings')->name('farm-profile');

});

Route::middleware(['auth', 'verified.email', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/account-settings', [AccountSettingsController::class, 'index'])->name('account-settings.index');
        Route::put('/account-settings', [AccountSettingsController::class, 'update'])->name('account-settings.update');

        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/print-data', [UserManagementController::class, 'printData'])->name('users.print-data');
        Route::get('/users/export/pdf', [UserManagementController::class, 'exportPdf'])->name('users.export.pdf');
        Route::get('/users/export/xlsx', [UserManagementController::class, 'exportExcel'])->name('users.export.xlsx');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/verify', [UserManagementController::class, 'verify'])->name('users.verify');

        Route::get('/farms', [FarmMonitoringController::class, 'index'])->name('farms.index');
        Route::get('/farms/print-data', [FarmMonitoringController::class, 'printData'])->name('farms.print-data');
        Route::get('/farms/export/pdf', [FarmMonitoringController::class, 'exportPdf'])->name('farms.export.pdf');
        Route::get('/farms/export/xlsx', [FarmMonitoringController::class, 'exportExcel'])->name('farms.export.xlsx');
        Route::get('/farms/{user}', [FarmMonitoringController::class, 'show'])->name('farms.show');
        Route::put('/farms/{user}', [FarmMonitoringController::class, 'update'])->name('farms.update');

        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    });
