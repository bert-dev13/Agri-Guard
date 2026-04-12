@extends('layouts.auth')

@php
    $isLocked = $lockedUntil instanceof \Illuminate\Support\Carbon && $lockedUntil->isFuture();
@endphp

@section('title', 'Verify reset code – AGRIGUARD')

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

                    <h2 class="auth-heading">Enter your code</h2>
                    <p class="auth-welcome">
                        We sent a code to <strong>{{ $email }}</strong>. Enter the 6-digit verification code below.
                    </p>

                    @if (session('status'))
                        <div class="auth-message success mt-6" role="status">{{ session('status') }}</div>
                    @endif

                    @if (session('message'))
                        <div class="auth-message success mt-6" role="status">{{ session('message') }}</div>
                    @endif

                    @error('email')
                        <div class="auth-message error mt-6" role="alert">{{ $message }}</div>
                    @enderror

                    @error('resend')
                        <div class="auth-message error mt-6" role="alert">{{ $message }}</div>
                    @enderror

                    @if ($isLocked)
                        <div class="auth-message error mt-6" role="alert">
                            Too many failed attempts. Try again {{ $lockedUntil->diffForHumans() }}.
                        </div>
                    @endif

                    @if (session('dev_password_reset_code') && config('app.debug'))
                        <div class="auth-message auth-message--dev mt-6" role="status">
                            <span class="block text-sm font-semibold mb-1">Dev code</span>
                            <span class="dev-code">{{ session('dev_password_reset_code') }}</span>
                        </div>
                    @endif

                    <form class="auth-form mt-6" method="POST" action="{{ route('password.verify') }}" novalidate>
                        @csrf

                        <div class="form-group {{ $errors->has('email') ? 'has-error' : '' }}">
                            <div class="auth-password-label-row">
                                <label for="email">Email</label>
                                @if ($emailReadonly)
                                    <a href="{{ route('password.request') }}" class="auth-small-link">Use a different email</a>
                                @endif
                            </div>
                            <div class="input-wrap">
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="{{ old('email', $email) }}"
                                    required
                                    autocomplete="email"
                                    placeholder="your@email.com"
                                    class="auth-input-readonly {{ $errors->has('email') ? 'error' : '' }}"
                                    @if ($emailReadonly) readonly @endif
                                    @if ($isLocked) readonly @endif
                                    @if (! $isLocked && ! $emailReadonly) autofocus @endif
                                />
                            </div>
                            @error('email')
                                <p class="error-message" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-group {{ $errors->has('code') ? 'has-error' : '' }} mt-5">
                            <label for="code">Verification code</label>
                            <div class="input-wrap">
                                <input
                                    type="text"
                                    id="code"
                                    name="code"
                                    value="{{ old('code') }}"
                                    inputmode="numeric"
                                    maxlength="6"
                                    autocomplete="one-time-code"
                                    pattern="[0-9]*"
                                    class="auth-code-input {{ $errors->has('code') ? 'error' : '' }}"
                                    required
                                    @if ($isLocked) disabled @elseif ($emailReadonly) autofocus @endif
                                />
                            </div>
                            @error('code')
                                <p class="error-message" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="auth-form-actions">
                            <button type="submit" class="btn-login w-full" @disabled($isLocked)>Verify code</button>
                        </div>

                        @unless ($isLocked)
                            <div class="auth-verify-resend">
                                <p class="auth-resend-text">Didn't receive a code?</p>
                                <button
                                    type="submit"
                                    class="btn-resend-code"
                                    formaction="{{ route('password.verify.resend') }}"
                                    formnovalidate
                                >
                                    Resend code
                                </button>
                            </div>
                        @endunless
                    </form>

                    <p class="auth-footer-link">
                        <a href="{{ route('password.request') }}">Request a new code</a>
                        ·
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
