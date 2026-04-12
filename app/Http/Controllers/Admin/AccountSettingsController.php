<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountSettingsController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.account-settings.index', [
            'admin' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $request->user();
        $isChangingPassword = filled($request->input('current_password'))
            || filled($request->input('new_password'))
            || filled($request->input('new_password_confirmation'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($admin->id),
            ],
            'current_password' => $isChangingPassword
                ? ['required', 'string']
                : ['nullable', 'string'],
            'new_password' => $isChangingPassword
                ? ['required', 'string', 'confirmed', Password::defaults(), 'different:current_password']
                : ['nullable', 'string', 'confirmed', Password::defaults(), 'different:current_password'],
            'new_password_confirmation' => $isChangingPassword
                ? ['required', 'string']
                : ['nullable', 'string'],
        ]);

        if ($isChangingPassword && ! Hash::check((string) $validated['current_password'], (string) $admin->password)) {
            return back()
                ->withErrors(['current_password' => 'Current password is incorrect.'])
                ->withInput($request->except(['current_password', 'new_password', 'new_password_confirmation']));
        }

        $admin->name = $validated['name'];
        $admin->email = $validated['email'];
        if ($isChangingPassword) {
            $admin->password = $validated['new_password'];
        }
        $admin->save();

        return redirect()
            ->route('admin.account-settings.index')
            ->with('success', 'Account settings updated successfully.');
    }
}
