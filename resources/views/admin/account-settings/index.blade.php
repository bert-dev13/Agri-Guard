@extends('layouts.admin')

@section('title', 'Account Settings - AGRIGUARD')
@section('body-class', 'admin-account-settings-page')

@section('content')
    <div class="admin-page">
        <section class="admin-account-settings__header">
            <div class="admin-dash-header">
                <div class="admin-dash-header__text">
                    <h1 class="admin-dash-header__title">
                        <span class="admin-dash-header__title-icon" aria-hidden="true"><i data-lucide="settings-2"></i></span>
                        <span>Account Settings</span>
                    </h1>
                    <p class="admin-dash-header__subtitle">Manage your admin account information</p>
                </div>
            </div>
        </section>

        @if (session('success'))
            <div class="admin-account-settings__flash admin-account-settings__flash--success" role="status" aria-live="polite">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="admin-account-settings__flash admin-account-settings__flash--error">
                <ul class="admin-account-settings__errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="admin-account-settings__card">
            <div class="admin-account-settings__info">
                <div class="admin-account-settings__badge-wrap">
                    <span class="admin-account-settings__badge admin-account-settings__badge--role">Role: Admin</span>
                    <span class="admin-account-settings__badge admin-account-settings__badge--status">
                        Account Status: {{ $admin->email_verified_at ? 'Verified' : 'Active' }}
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.account-settings.update') }}" class="admin-account-settings__form" autocomplete="off">
                @csrf
                @method('PUT')

                <div class="admin-account-settings__grid">
                    <label class="admin-account-settings__field" for="admin-account-name">
                        <span class="admin-account-settings__label">Full Name</span>
                        <input
                            id="admin-account-name"
                            type="text"
                            name="name"
                            class="admin-account-settings__input"
                            value="{{ old('name', $admin->name) }}"
                            required
                        >
                    </label>

                    <label class="admin-account-settings__field" for="admin-account-email">
                        <span class="admin-account-settings__label">Email Address</span>
                        <input
                            id="admin-account-email"
                            type="email"
                            name="email"
                            class="admin-account-settings__input"
                            value="{{ old('email', $admin->email) }}"
                            required
                        >
                    </label>

                    <label class="admin-account-settings__field admin-account-settings__field--full" for="admin-account-current-password">
                        <span class="admin-account-settings__label">Current Password</span>
                        <input
                            id="admin-account-current-password"
                            type="password"
                            name="current_password"
                            class="admin-account-settings__input"
                            autocomplete="current-password"
                        >
                    </label>

                    <label class="admin-account-settings__field" for="admin-account-new-password">
                        <span class="admin-account-settings__label">New Password</span>
                        <input
                            id="admin-account-new-password"
                            type="password"
                            name="new_password"
                            class="admin-account-settings__input"
                            autocomplete="new-password"
                        >
                    </label>

                    <label class="admin-account-settings__field" for="admin-account-new-password-confirmation">
                        <span class="admin-account-settings__label">Confirm New Password</span>
                        <input
                            id="admin-account-new-password-confirmation"
                            type="password"
                            name="new_password_confirmation"
                            class="admin-account-settings__input"
                            autocomplete="new-password"
                        >
                    </label>
                </div>

                <div class="admin-account-settings__actions">
                    <button type="reset" class="admin-account-settings__btn admin-account-settings__btn--reset">Reset</button>
                    <button type="submit" class="admin-account-settings__btn admin-account-settings__btn--primary">Save Changes</button>
                </div>
            </form>
        </section>
    </div>
@endsection
