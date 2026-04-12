@extends('layouts.auth')

@section('title', 'Forgot Password – AGRIGUARD')

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

                    <div class="flex items-center gap-3 mb-2">
                        <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#00809D]/10 text-[#00809D] shrink-0" aria-hidden="true">
                            <i data-lucide="key-round" class="w-5 h-5"></i>
                        </span>
                        <h2 class="auth-heading mb-0">Forgot password</h2>
                    </div>
                    <p class="auth-welcome">
                        Enter the email address for your account. If it is registered, we will send you a verification code to reset your password.
                    </p>

                    @if (session('message'))
                        <div class="auth-message error mt-6" role="alert">{{ session('message') }}</div>
                    @endif

                    <form class="auth-form mt-6" method="POST" action="{{ route('password.send') }}" novalidate>
                        @csrf

                        <div class="form-group {{ $errors->has('email') ? 'has-error' : '' }}">
                            <label for="email">Email</label>
                            <div class="input-wrap">
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="{{ old('email', session('password_reset_email')) }}"
                                    required
                                    autocomplete="email"
                                    placeholder="your@email.com"
                                    class="{{ $errors->has('email') ? 'error' : '' }}"
                                    autofocus
                                />
                            </div>
                            @error('email')
                                <p class="error-message" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="auth-form-actions">
                            <button type="submit" class="btn-login w-full">Send verification code</button>
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

@push('scripts')
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    </script>
@endpush
