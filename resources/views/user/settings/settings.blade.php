@php
    $inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-900 text-sm shadow-sm focus:border-[#2E7D32] focus:outline-none focus:ring-2 focus:ring-[#2E7D32]/20 transition-colors';
    $inputErrorClass = 'border-red-400 focus:border-red-400 focus:ring-red-400/20';
    $labelClass = 'block text-sm font-semibold text-slate-700 mb-1.5';
    $btnPrimary = 'inline-flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold text-white bg-[#2E7D32] hover:bg-[#266B29] focus:outline-none focus:ring-2 focus:ring-[#2E7D32] focus:ring-offset-2 transition-colors shadow-sm';
    $locationFull = $user->farm_location_display;
@endphp
@extends('layouts.user')

@section('title', 'Settings – AGRIGUARD')

@section('body-class', 'settings-page min-h-screen bg-[#F4F6F5]')

@section('main-class', 'pt-20')

@section('content')
        <section class="py-5 sm:py-6 pb-20">
            <div class="max-w-3xl mx-auto px-4 sm:px-5">
                @if (session('success'))
                    <div class="mb-5 rounded-2xl bg-[#66BB6A]/15 border border-[#66BB6A]/30 text-[#1B5E20] px-4 py-3.5 text-sm font-medium flex items-center gap-2" role="alert">
                        <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32] shrink-0"></i>
                        {{ session('success') }}
                    </div>
                @endif

                <header class="ag-card overflow-hidden mb-5" style="background: linear-gradient(135deg, #2E7D32 0%, #388E3C 50%, #43A047 100%);">
                    <div class="px-6 py-6 sm:px-7 sm:py-7 flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight">Settings</h1>
                            <p class="mt-2 text-white/90 text-sm">Manage your farm profile and system preferences.</p>
                        </div>
                        <span class="flex items-center justify-center w-14 h-14 rounded-2xl bg-white/20 shrink-0">
                            <i data-lucide="settings" class="w-8 h-8 text-white"></i>
                        </span>
                    </div>
                </header>

                <div id="user-profile" class="ag-card mb-5 p-5 sm:p-6 scroll-mt-24">
                    <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#66BB6A]/20 shrink-0">
                            <i data-lucide="user" class="w-5 h-5 text-[#2E7D32]"></i>
                        </span>
                        User Profile
                    </h2>
                    <form method="POST" action="{{ route('settings.account.update') }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="name" class="{{ $labelClass }}">Full Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" class="{{ $inputClass }} @error('name') {{ $inputErrorClass }} @enderror" placeholder="Your name" />
                            @error('name')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="email" class="{{ $labelClass }}">Email Address</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="{{ $inputClass }} @error('email') {{ $inputErrorClass }} @enderror" placeholder="your@email.com" />
                            @error('email')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <p class="{{ $labelClass }}">Email verification</p>
                            @if ($user->email_verified_at)
                                <p class="text-sm text-slate-600 bg-[#E8F5E9]/60 border border-[#66BB6A]/25 rounded-xl px-4 py-3">
                                    Verified on {{ $user->email_verified_at->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A') }}.
                                    <span class="block mt-1 text-xs text-slate-500">The one-time code is removed from the database after verification; that is normal.</span>
                                </p>
                            @else
                                <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200/80 rounded-xl px-4 py-3">This address is not verified yet. Complete verification from the link sent when you registered.</p>
                            @endif
                        </div>
                        <button type="submit" class="{{ $btnPrimary }}">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Update profile
                        </button>
                    </form>
                    <div id="password-section" class="mt-5 pt-5 border-t border-slate-100 scroll-mt-24">
                        <h3 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-slate-100 shrink-0">
                                <i data-lucide="key" class="w-4 h-4 text-slate-600"></i>
                            </span>
                            Password
                        </h3>
                        <form id="password-form" method="POST" action="{{ route('settings.password.update') }}" class="space-y-4">
                            @csrf
                            @method('PUT')
                            <div>
                                <label for="current_password" class="{{ $labelClass }}">Current password</label>
                                <input type="password" id="current_password" name="current_password" required autocomplete="current-password" class="{{ $inputClass }} @error('current_password') {{ $inputErrorClass }} @enderror" />
                                @error('current_password')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="password" class="{{ $labelClass }}">New password</label>
                                <input type="password" id="password" name="password" required autocomplete="new-password" class="{{ $inputClass }} @error('password') {{ $inputErrorClass }} @enderror" />
                                @error('password')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="password_confirmation" class="{{ $labelClass }}">Confirm new password</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="{{ $inputClass }}" />
                            </div>
                            <button type="submit" class="{{ $btnPrimary }}">
                                <i data-lucide="lock" class="w-4 h-4"></i>
                                Update password
                            </button>
                        </form>
                    </div>
                </div>

                @php
                    $coverageDefault = config('agriguard.coverage_area.default', 'amulung');
                    $coverageArea = config('agriguard.coverage_area.areas.' . $coverageDefault, []);
                    $coverageLabel = $coverageArea['label'] ?? 'Amulung, Cagayan';
                @endphp
                <div id="farm-profile" class="ag-card mb-5 p-5 sm:p-6 scroll-mt-24 settings-farm-profile">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#66BB6A]/20 shrink-0">
                                <i data-lucide="tractor" class="w-5 h-5 text-[#2E7D32]"></i>
                            </span>
                            Farm Profile
                        </h2>
                        <p class="text-xs text-slate-500 max-w-[16rem] md:max-w-none md:text-right leading-snug">{{ $coverageLabel }} only.</p>
                    </div>
                    <form method="POST" action="{{ route('settings.farm.update') }}" class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="farm_municipality" class="{{ $labelClass }}">Municipality</label>
                            <select
                                id="farm_municipality"
                                name="farm_municipality"
                                required
                                class="{{ $inputClass }} @error('farm_municipality') {{ $inputErrorClass }} @enderror"
                                data-old="{{ old('farm_municipality', $user->farm_municipality) }}"
                            >
                                <option value="">Select municipality</option>
                                @foreach ($municipalities as $mun)
                                    <option value="{{ $mun }}" @selected(old('farm_municipality', $user->farm_municipality) === $mun)>{{ $mun }}</option>
                                @endforeach
                            </select>
                            @error('farm_municipality')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="farm_barangay_code" class="{{ $labelClass }}">Barangay</label>
                            <select id="farm_barangay_code" name="farm_barangay_code" required class="{{ $inputClass }} @error('farm_barangay_code') {{ $inputErrorClass }} @enderror" data-api-url="{{ url('/api/barangays') }}" data-municipality-select="farm_municipality" data-old="{{ old('farm_barangay_code', $user->farm_barangay_code ?? '') }}">
                                <option value="">Select barangay</option>
                            </select>
                            @error('farm_barangay_code')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Farm location</p>
                            <p class="text-sm text-slate-700 bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5">{{ $locationFull }}</p>
                        </div>
                        <div>
                            <label for="crop_type" class="{{ $labelClass }}">Crop</label>
                            <select id="crop_type" name="crop_type" required class="{{ $inputClass }} @error('crop_type') {{ $inputErrorClass }} @enderror">
                                <option value="">Select crop</option>
                                <option value="Rice" {{ old('crop_type', $user->crop_type) === 'Rice' ? 'selected' : '' }}>Rice</option>
                                <option value="Corn" {{ old('crop_type', $user->crop_type) === 'Corn' ? 'selected' : '' }}>Corn</option>
                            </select>
                            @error('crop_type')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Growth stage</p>
                            <p class="text-sm text-slate-700 bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5">
                                {{ app(\App\Services\CropTimelineService::class)->inferExpectedStageFromPlanting($user)['label'] }}
                                <span class="block text-xs text-slate-500 mt-1 font-normal normal-case">Set from crop type and planting date. Adjust in Crop Progress if the field differs.</span>
                            </p>
                        </div>
                        <div>
                            <label for="planting_date" class="{{ $labelClass }}">Planting date</label>
                            <input type="date" id="planting_date" name="planting_date" value="{{ old('planting_date', $user->planting_date?->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required class="{{ $inputClass }} @error('planting_date') {{ $inputErrorClass }} @enderror" />
                            @error('planting_date')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="farm_area" class="{{ $labelClass }}">Area (m²)</label>
                            <input type="number" id="farm_area" name="farm_area" value="{{ old('farm_area', $user->farm_area) }}" placeholder="1200" min="0.01" step="any" required class="{{ $inputClass }} @error('farm_area') {{ $inputErrorClass }} @enderror" inputmode="decimal" />
                            @error('farm_area')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Map pin</p>
                                    <input type="hidden" id="farm_lat" name="farm_lat" value="{{ old('farm_lat', $user->farm_lat) }}" />
                                    <input type="hidden" id="farm_lng" name="farm_lng" value="{{ old('farm_lng', $user->farm_lng) }}" />
                                    <p id="farm-coords-display" class="text-sm text-slate-800 tabular-nums font-medium @if(!old('farm_lat', $user->farm_lat)) hidden @endif"><span id="farm-coords-text">{{ old('farm_lat', $user->farm_lat) && old('farm_lng', $user->farm_lng) ? number_format((float)old('farm_lat', $user->farm_lat), 5) . ', ' . number_format((float)old('farm_lng', $user->farm_lng), 5) : '' }}</span></p>
                                    <p id="farm-coords-placeholder" class="text-sm text-slate-400 @if(old('farm_lat', $user->farm_lat)) hidden @endif">Not set</p>
                                </div>
                                <button type="button" id="btn-use-current-location" class="inline-flex items-center justify-center gap-2 shrink-0 px-4 py-2 rounded-xl text-sm font-medium text-[#2E7D32] bg-white border border-[#66BB6A]/40 hover:bg-[#66BB6A]/10 transition-colors">
                                    <i data-lucide="map-pin" class="w-4 h-4"></i>
                                    Use location
                                </button>
                            </div>
                        </div>
                        <div class="md:col-span-2 pt-1">
                            <button type="submit" class="{{ $btnPrimary }} w-full sm:w-auto justify-center">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Save farm
                            </button>
                        </div>
                    </form>
                </div>

                <div class="ag-card p-5 sm:p-6">
                    <h2 class="text-base font-bold text-slate-900 mb-4">Account Actions</h2>
                    <div class="space-y-3">
                        <a href="#password-section" class="w-full flex items-center gap-3 p-4 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition-colors text-left no-underline text-inherit">
                            <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 shrink-0">
                                <i data-lucide="lock" class="w-5 h-5 text-slate-600"></i>
                            </span>
                            <span class="font-medium text-slate-800">Change password</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-3 p-4 rounded-xl border border-red-200 bg-red-50 hover:bg-red-100 transition-colors text-left">
                                <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-red-100 shrink-0">
                                    <i data-lucide="log-out" class="w-5 h-5 text-red-600"></i>
                                </span>
                                <span class="font-medium text-red-800">Logout Account</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
@endsection
