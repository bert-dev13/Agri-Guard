@extends('layouts.auth')

@section('title', 'Set new password – AGRIGUARD')

@section('content')
    <section class="auth-page relative flex flex-1 flex-col items-center justify-center min-h-0 pt-24 sm:pt-28 lg:pt-32 px-4 sm:px-6 lg:px-8 pb-6 sm:pb-8">
        <div class="auth-orb-1" aria-hidden="true"></div>
        <div class="auth-orb-2" aria-hidden="true"></div>
        <div class="auth-container">
            <div class="auth-form-wrap">
                <div class="auth-card">
                    <a href="{{ url('/') }}" class="auth-brand">
                        <span class="auth-brand-logo">
                            <img src="{{ asset('images/agriguard-logo.png') }}" alt="AGRIGUARD" />
                        </span>
                        <span class="auth-brand-wordmark">AGRIGUARD</span>
                    </a>

                    <h2 class="auth-heading">Choose a new password</h2>
                    <p class="auth-welcome">
                        @if ($email !== '')
                            You are resetting the password for <strong>{{ $email }}</strong>.
                        @else
                            Enter and confirm your new password below.
                        @endif
                    </p>

                    @if (session('message'))
                        <div class="auth-message success mt-6" role="status">{{ session('message') }}</div>
                    @endif

                    <form class="auth-form mt-6" method="POST" action="{{ route('password.reset') }}" novalidate>
                        @csrf

                        <div class="form-group {{ $errors->has('password') ? 'has-error' : '' }}">
                            <label for="password">New password</label>
                            <div class="input-wrap with-password-toggle">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    required
                                    autocomplete="new-password"
                                    class="{{ $errors->has('password') ? 'error' : '' }}"
                                    data-password-input
                                />
                                <x-auth.password-toggle-button />
                            </div>
                            @error('password')
                                <p class="error-message" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-group {{ $errors->has('password_confirmation') ? 'has-error' : '' }} mt-5">
                            <label for="password_confirmation">Confirm new password</label>
                            <div class="input-wrap with-password-toggle">
                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    class="{{ $errors->has('password_confirmation') ? 'error' : '' }}"
                                    data-password-input
                                />
                                <x-auth.password-toggle-button />
                            </div>
                            @error('password_confirmation')
                                <p class="error-message" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="auth-form-actions">
                            <button type="submit" class="btn-login w-full">Update password</button>
                        </div>
                    </form>

                    <p class="auth-footer-link">
                        <a href="{{ route('login') }}">Back to login</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
