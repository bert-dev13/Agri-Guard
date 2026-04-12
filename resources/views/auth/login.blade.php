@extends('layouts.auth')

@section('title', 'Login – AGRIGUARD')

@section('content')
    <section class="auth-page relative flex flex-1 flex-col items-center justify-center min-h-0 pt-24 sm:pt-28 lg:pt-32 px-4 sm:px-6 lg:px-8 pb-6 sm:pb-8">
        <div class="auth-container">
            <div class="auth-form-wrap">
                <div class="auth-card">
                    <a href="{{ url('/') }}" class="auth-brand">
                        <span class="auth-brand-logo">
                            <img src="{{ asset('images/agriguard-logo.png') }}" alt="AGRIGUARD" />
                        </span>
                        <span class="auth-brand-wordmark">AGRIGUARD</span>
                    </a>

                    <h2 class="auth-heading">Welcome back</h2>
                    <p class="auth-welcome">Sign in to access your weather insights and farm advisories.</p>

                    @if (session('success'))
                        <div class="auth-message success mt-6" role="status">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form class="auth-form" method="POST" action="{{ route('login') }}" novalidate>
                        @csrf

                        <div class="form-group {{ $errors->has('email') ? 'has-error' : '' }}">
                            <label for="email">Email</label>
                            <div class="input-wrap">
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autocomplete="username"
                                    placeholder="your@email.com"
                                    class="{{ $errors->has('email') ? 'error' : '' }}"
                                    autofocus
                                />
                            </div>
                            @error('email')
                                <p class="error-message" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-group {{ $errors->has('password') ? 'has-error' : '' }} mt-5">
                            <div class="auth-password-label-row">
                                <label for="password">Password</label>
                                <a href="{{ route('password.request') }}" class="auth-small-link">Forgot password?</a>
                            </div>
                            <div class="input-wrap with-password-toggle">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    class="{{ $errors->has('password') ? 'error' : '' }}"
                                    data-password-input
                                />

                                <x-auth.password-toggle-button />
                            </div>
                            @error('password')
                                <p class="error-message" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="auth-form-actions">
                            <button type="submit" class="btn-login w-full">Login</button>
                        </div>
                    </form>

                    <p class="auth-footer-link">
                        Don't have an account?
                        <a href="{{ route('register') }}">Register</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
