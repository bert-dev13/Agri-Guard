@extends('layouts.auth')

@section('title', 'Register')

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
                            <a href="{{ url('/') }}" class="auth-brand" aria-label="AGRIGUARD — home">
                                <span class="auth-brand-logo">
                                    <img src="{{ asset('images/agriguard-logo.png') }}" alt="" decoding="async" />
                                </span>
                            </a>

                            <div class="text-center">
                                <h2 class="auth-heading mb-0">Create your account</h2>
                                <p class="auth-welcome">Join AGRIGUARD for weather insights and farm advisories.</p>
                            </div>

                            @if ($errors->any())
                                <div class="auth-message error relative" role="alert">
                                    <ul class="list-disc list-inside text-left">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form class="auth-form" method="POST" action="{{ route('register') }}" novalidate>
                                @csrf

                                <div class="space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                                        <div class="form-group {{ $errors->has('name') ? 'has-error' : '' }}">
                                            <label for="name">Full name</label>
                                            <div class="input-wrap">
                                                <input
                                                    type="text"
                                                    id="name"
                                                    name="name"
                                                    value="{{ old('name') }}"
                                                    required
                                                    autocomplete="name"
                                                    class="{{ $errors->has('name') ? 'error' : '' }}"
                                                    placeholder="Your full name"
                                                />
                                            </div>
                                            @error('name')
                                                <p class="error-message" role="alert">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="form-group {{ $errors->has('email') ? 'has-error' : '' }}">
                                            <label for="email">Email</label>
                                            <div class="input-wrap">
                                                <input
                                                    type="email"
                                                    id="email"
                                                    name="email"
                                                    value="{{ old('email') }}"
                                                    required
                                                    autocomplete="email"
                                                    class="{{ $errors->has('email') ? 'error' : '' }}"
                                                    placeholder="name@example.com"
                                                />
                                            </div>
                                            @error('email')
                                                <p class="error-message" role="alert">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                                        <div class="form-group {{ $errors->has('password') ? 'has-error' : '' }}">
                                            <label for="password">Password</label>
                                            <div class="input-wrap">
                                                <input
                                                    type="password"
                                                    id="password"
                                                    name="password"
                                                    required
                                                    autocomplete="new-password"
                                                    class="{{ $errors->has('password') ? 'error' : '' }}"
                                                    placeholder="Enter password"
                                                />
                                            </div>
                                            @error('password')
                                                <p class="error-message" role="alert">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="form-group {{ $errors->has('password_confirmation') ? 'has-error' : '' }}">
                                            <label for="password_confirmation">Confirm password</label>
                                            <div class="input-wrap">
                                                <input
                                                    type="password"
                                                    id="password_confirmation"
                                                    name="password_confirmation"
                                                    required
                                                    autocomplete="new-password"
                                                    class="{{ $errors->has('password_confirmation') ? 'error' : '' }}"
                                                    placeholder="Confirm password"
                                                />
                                            </div>
                                            @error('password_confirmation')
                                                <p class="error-message" role="alert">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="pt-4 border-t border-slate-100/80">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                                            <div class="form-group {{ $errors->has('farm_barangay_code') ? 'has-error' : '' }}">
                                                <label for="farm_barangay_code">
                                                    Barangay
                                                    <span class="ml-1 text-xs font-semibold text-slate-500">(required)</span>
                                                </label>

                                                <div class="input-wrap">
                                                    <select
                                                        id="farm_barangay_code"
                                                        name="farm_barangay_code"
                                                        required
                                                        class="{{ $errors->has('farm_barangay_code') ? 'error' : '' }}"
                                                        data-api-url="{{ url('/api/amulung-barangays') }}"
                                                        data-old="{{ old('farm_barangay_code') }}"
                                                    >
                                                        <option value="">Select barangay</option>
                                                    </select>
                                                </div>

                                                @error('farm_barangay_code')
                                                    <p class="error-message" role="alert">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="form-group {{ $errors->has('crop_type') ? 'has-error' : '' }}">
                                                <label for="crop_type">
                                                    Crop type
                                                    <span class="ml-1 text-xs font-semibold text-slate-500">(required)</span>
                                                </label>
                                                <div class="input-wrap">
                                                    <select
                                                        id="crop_type"
                                                        name="crop_type"
                                                        required
                                                        class="{{ $errors->has('crop_type') ? 'error' : '' }}"
                                                    >
                                                        <option value="" @selected(old('crop_type', '') === '')>Select crop…</option>
                                                        <option value="Rice" @selected(old('crop_type') === 'Rice')>Rice</option>
                                                        <option value="Corn" @selected(old('crop_type') === 'Corn')>Corn</option>
                                                    </select>
                                                </div>
                                                @error('crop_type')
                                                    <p class="error-message" role="alert">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="form-group {{ $errors->has('planting_date') ? 'has-error' : '' }}">
                                                <label for="planting_date">
                                                    Planting date
                                                    <span class="ml-1 text-xs font-semibold text-slate-500">(required)</span>
                                                </label>
                                                <div class="input-wrap">
                                                    <input
                                                        type="date"
                                                        id="planting_date"
                                                        name="planting_date"
                                                        value="{{ old('planting_date') }}"
                                                        max="{{ now()->format('Y-m-d') }}"
                                                        required
                                                        class="{{ $errors->has('planting_date') ? 'error' : '' }}"
                                                    />
                                                </div>
                                                @error('planting_date')
                                                    <p class="error-message" role="alert">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="form-group {{ $errors->has('farm_area') ? 'has-error' : '' }}">
                                                <label for="farm_area">
                                                    Farm area (m²)
                                                    <span class="ml-1 text-xs font-semibold text-slate-500">(required)</span>
                                                </label>
                                                <div class="input-wrap">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        id="farm_area"
                                                        name="farm_area"
                                                        value="{{ old('farm_area') }}"
                                                        required
                                                        min="0.01"
                                                        class="{{ $errors->has('farm_area') ? 'error' : '' }}"
                                                        placeholder="e.g., 2500"
                                                        inputmode="decimal"
                                                    />
                                                </div>
                                                @error('farm_area')
                                                    <p class="error-message" role="alert">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pt-2">
                                        <div class="auth-form-actions">
                                            <button type="submit" class="btn-register w-full">Register</button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <p class="auth-footer-link">
                                <a href="{{ route('login') }}">Log in</a>
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <x-footer />
    </div>
@endsection
