<?php

namespace App\Http\Controllers;

use App\Services\FarmWeatherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Show the settings page (account + farm profile) for the authenticated user.
     */
    public function show(): View
    {
        $user = Auth::user();

        return view('user.settings.settings', [
            'user' => $user,
        ]);
    }

    /**
     * Update the authenticated user's account information (name, email).
     */
    public function updateAccount(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ], [
            'name.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already in use.',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        return redirect()->route('settings')
            ->with('success', 'Account information updated successfully.');
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'Current password is required.',
            'current_password.current_password' => 'The current password is incorrect.',
            'password.required' => 'New password is required.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        Auth::user()->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return redirect()->route('settings')
            ->with('success', 'Password updated successfully.');
    }

    /**
     * Update the authenticated user's farm information.
     */
    public function updateFarm(Request $request, FarmWeatherService $farmWeather): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'crop_type' => ['required', 'string', 'in:Rice,Corn'],
            'farming_stage' => ['nullable', 'string', 'in:land_preparation,planting,early_growth,growing,flowering_fruiting,harvesting'],
            'planting_date' => ['required', 'date'],
            'farm_area' => ['required', 'numeric', 'min:0.01'],
            'farm_barangay' => ['required', 'string', 'max:20'],
            'farm_municipality' => ['required', 'string', 'in:Amulung'],
            'farm_lat' => ['nullable', 'numeric', 'min:-90', 'max:90'],
            'farm_lng' => ['nullable', 'numeric', 'min:-180', 'max:180'],
        ], [
            'crop_type.required' => 'Crop type is required.',
            'crop_type.in' => 'Crop type must be Rice or Corn.',
            'planting_date.required' => 'Planting date is required.',
            'planting_date.date' => 'Please enter a valid planting date.',
            'farm_area.required' => 'Farm size is required.',
            'farm_area.numeric' => 'Farm size must be a number.',
            'farm_area.min' => 'Farm size must be greater than 0.',
            'farm_barangay.required' => 'Barangay is required.',
            'farm_municipality.required' => 'Municipality is required.',
            'farm_municipality.in' => 'Municipality must be Amulung.',
        ]);

        $user->update([
            'crop_type' => $validated['crop_type'],
            'farming_stage' => $validated['farming_stage'] ?? null,
            'planting_date' => $validated['planting_date'],
            'farm_area' => (float) $validated['farm_area'],
            'farm_barangay' => $validated['farm_barangay'],
            'farm_municipality' => $validated['farm_municipality'],
            'farm_lat' => isset($validated['farm_lat']) ? (float) $validated['farm_lat'] : null,
            'farm_lng' => isset($validated['farm_lng']) ? (float) $validated['farm_lng'] : null,
        ]);

        $farmWeather->invalidateCacheForUser($user);

        return redirect()->route('settings')
            ->with('success', 'Farm profile updated successfully.');
    }
}
