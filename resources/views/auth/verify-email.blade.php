@extends('layouts.auth')

@php
    $isLocked = $lockedUntil?->isFuture();
@endphp

@section('title', 'Verify Email – AGRIGUARD')

@section('body-class', 'auth-verify-page m-0 flex min-h-screen min-h-[100dvh] flex-col overflow-x-clip bg-[#F8FAFC] auth-layout')

@section('auth-shell')
    <div class="relative flex w-full flex-1 flex-col bg-[#F8FAFC]">
        <div
            class="pointer-events-none absolute inset-0 z-0 bg-[linear-gradient(135deg,rgba(0,128,157,0.04),transparent,rgba(16,185,129,0.04))]"
            aria-hidden="true"
        ></div>

        <x-public-navbar />

        <main class="main-content relative z-10 flex w-full grow shrink-0 basis-auto flex-col">
            <section class="auth-page auth-page-fullbleed relative flex w-full grow shrink-0 basis-auto flex-col items-center justify-center px-4 pb-10 pt-24 sm:px-6 sm:pb-10 sm:pt-28 lg:px-8 lg:pt-32">
                <div class="auth-orb-1" aria-hidden="true"></div>
                <div class="auth-orb-2" aria-hidden="true"></div>
                <div class="auth-container auth-container-register">
                    <div class="auth-form-wrap">
                        <div class="auth-card">
                            <a href="{{ url('/') }}" class="auth-brand">
                                <span class="auth-brand-logo">
                                    <img src="{{ asset('images/agriguard-logo.png') }}" alt="AGRIGUARD" />
                                </span>
                                <span class="auth-brand-wordmark">AGRIGUARD</span>
                            </a>

                            <div class="text-center">
                                <h2 class="auth-heading mb-0">Verify your email</h2>
                                <p class="auth-welcome">
                                    We sent a 6-digit code to <strong>{{ $email }}</strong>. Enter it below to finish signing up.
                                </p>
                            </div>

                            @if (session('message'))
                                <div class="auth-message success mt-6" role="status">{{ session('message') }}</div>
                            @endif

                            @error('resend')
                                <div class="auth-message error mt-6" role="alert">{{ $message }}</div>
                            @enderror

                            @if ($isLocked)
                                <div class="auth-message error mt-6" role="alert">
                                    Too many attempts. Try again {{ $lockedUntil->diffForHumans() }}.
                                </div>
                            @endif

                            @if ($devCode && config('app.debug'))
                                <div class="auth-message auth-message--dev mt-6" role="status">
                                    <span class="block text-sm font-semibold mb-1">Dev code</span>
                                    <span class="dev-code">{{ $devCode }}</span>
                                </div>
                            @endif

                            @if ($expiresAt)
                                <p
                                    class="auth-countdown mt-6 text-center {{ $expiresAt->isPast() ? 'is-expired' : '' }}"
                                    data-expires-at="{{ $expiresAt->toIso8601String() }}"
                                    id="verify-code-countdown"
                                >
                                    <span class="auth-countdown-label">Code expires in</span>
                                    <span class="auth-countdown-time" data-countdown-display>—</span>
                                </p>
                            @endif

                            <form class="auth-form mt-6" method="POST" action="{{ route('verification.submit') }}">
                                @csrf
                                <div class="form-group {{ $errors->has('code') ? 'has-error' : '' }}">
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
                                            @if ($isLocked) disabled @endif
                                            @unless ($isLocked) autofocus @endunless
                                        />
                                    </div>
                                    @error('code')
                                        <p class="error-message" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="auth-form-actions">
                                    <button type="submit" class="btn-register w-full" @disabled($isLocked)>Verify</button>
                                </div>
                            </form>

                            @unless ($isLocked)
                                <div class="auth-verify-resend">
                                    <p class="auth-resend-text">Didn't receive a code?</p>
                                    <form method="POST" action="{{ route('verification.resend') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="btn-resend-code">Resend code</button>
                                    </form>
                                </div>
                            @endunless

                            <p class="auth-footer-link">
                                <a href="{{ route('login') }}">Back to login</a>
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <x-footer />
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    </script>
@endpush
