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

                <div class="ag-card mb-5 p-5 sm:p-6">
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
                        <button type="submit" class="{{ $btnPrimary }}">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Update profile
                        </button>
                    </form>
                    <div class="mt-5 pt-5 border-t border-slate-100">
                        <p class="text-sm font-semibold text-slate-700 mb-2">Password</p>
                        <button type="button" onclick="document.getElementById('password-form').classList.toggle('hidden')" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-[#2E7D32] bg-[#66BB6A]/15 hover:bg-[#66BB6A]/25 transition-colors">
                            <i data-lucide="key" class="w-4 h-4"></i>
                            Change Password
                        </button>
                    </div>
                    <div id="password-form" class="hidden mt-5 pt-5 border-t border-slate-100">
                        <form method="POST" action="{{ route('settings.password.update') }}" class="space-y-4">
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

                <div id="farm-profile" class="ag-card mb-5 p-5 sm:p-6 scroll-mt-24">
                    <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#66BB6A]/20 shrink-0">
                            <i data-lucide="tractor" class="w-5 h-5 text-[#2E7D32]"></i>
                        </span>
                        Farm Profile
                    </h2>
                    @php
                        $coverageDefault = config('agriguard.coverage_area.default', 'amulung');
                        $coverageArea = config('agriguard.coverage_area.areas.' . $coverageDefault, []);
                        $coverageLabel = $coverageArea['label'] ?? 'Amulung, Cagayan';
                    @endphp
                    <p class="text-sm text-slate-600 bg-[#E8F5E9]/60 border border-[#66BB6A]/30 rounded-xl px-4 py-3 mb-4 flex items-start gap-2">
                        <i data-lucide="info" class="w-5 h-5 text-[#2E7D32] shrink-0 mt-0.5"></i>
                        <span>AGRIGUARD currently supports farms within <strong>{{ $coverageLabel }}</strong> only.</span>
                    </p>
                    <form method="POST" action="{{ route('settings.farm.update') }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="farm_municipality" value="Amulung" />
                        <div>
                            <label for="farm_location_display" class="{{ $labelClass }}">Farm Location</label>
                            <p class="text-sm text-slate-600 bg-[#F4F6F5] rounded-xl px-4 py-3">{{ $locationFull }}</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="crop_type" class="{{ $labelClass }}">Crop Type</label>
                                <select id="crop_type" name="crop_type" required class="{{ $inputClass }} @error('crop_type') {{ $inputErrorClass }} @enderror">
                                    <option value="">Select crop...</option>
                                    <option value="Rice" {{ old('crop_type', $user->crop_type) === 'Rice' ? 'selected' : '' }}>Rice</option>
                                    <option value="Corn" {{ old('crop_type', $user->crop_type) === 'Corn' ? 'selected' : '' }}>Corn</option>
                                </select>
                                @error('crop_type')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="farming_stage" class="{{ $labelClass }}">Farming Stage</label>
                                <select id="farming_stage" name="farming_stage" class="{{ $inputClass }} @error('farming_stage') {{ $inputErrorClass }} @enderror">
                                    <option value="">Select stage...</option>
                                    <option value="land_preparation" {{ old('farming_stage', $user->farming_stage) === 'land_preparation' ? 'selected' : '' }}>Land Preparation</option>
                                    <option value="planting" {{ old('farming_stage', $user->farming_stage) === 'planting' ? 'selected' : '' }}>Planting</option>
                                    <option value="early_growth" {{ old('farming_stage', $user->farming_stage) === 'early_growth' ? 'selected' : '' }}>Early Growth</option>
                                    <option value="growing" {{ old('farming_stage', $user->farming_stage) === 'growing' ? 'selected' : '' }}>Growing Stage</option>
                                    <option value="flowering_fruiting" {{ old('farming_stage', $user->farming_stage) === 'flowering_fruiting' ? 'selected' : '' }}>Flowering / Fruiting</option>
                                    <option value="harvesting" {{ old('farming_stage', $user->farming_stage) === 'harvesting' ? 'selected' : '' }}>Harvesting</option>
                                </select>
                                @error('farming_stage')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="planting_date" class="{{ $labelClass }}">Planting Date</label>
                                <input type="date" id="planting_date" name="planting_date" value="{{ old('planting_date', $user->planting_date?->format('Y-m-d')) }}" required class="{{ $inputClass }} @error('planting_date') {{ $inputErrorClass }} @enderror" />
                                @error('planting_date')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="farm_area" class="{{ $labelClass }}">Farm Area (m²)</label>
                                <input type="number" id="farm_area" name="farm_area" value="{{ old('farm_area', $user->farm_area) }}" placeholder="e.g. 1200" min="0.01" step="any" required class="{{ $inputClass }} @error('farm_area') {{ $inputErrorClass }} @enderror" inputmode="decimal" />
                                @error('farm_area')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="farm_barangay" class="{{ $labelClass }}">Barangay</label>
                                <select id="farm_barangay" name="farm_barangay" required class="{{ $inputClass }} @error('farm_barangay') {{ $inputErrorClass }} @enderror" data-api-url="{{ url('/api/amulung-barangays') }}" data-old="{{ old('farm_barangay', $user->farm_barangay) }}">
                                    <option value="">Select barangay</option>
                                </select>
                                @error('farm_barangay')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="pt-2 border-t border-slate-100">
                            <p class="text-sm font-semibold text-slate-700 mb-1.5">Farm map location</p>
                            <p class="text-xs text-slate-500 mb-2">Set your farm coordinates for weather and advisory. Optional: use current location.</p>
                            <input type="hidden" id="farm_lat" name="farm_lat" value="{{ old('farm_lat', $user->farm_lat) }}" />
                            <input type="hidden" id="farm_lng" name="farm_lng" value="{{ old('farm_lng', $user->farm_lng) }}" />
                            <p id="farm-coords-display" class="text-sm text-slate-600 mb-2 @if(!old('farm_lat', $user->farm_lat)) hidden @endif">Coordinates: <span id="farm-coords-text">{{ old('farm_lat', $user->farm_lat) && old('farm_lng', $user->farm_lng) ? number_format((float)old('farm_lat', $user->farm_lat), 5) . ', ' . number_format((float)old('farm_lng', $user->farm_lng), 5) : '' }}</span></p>
                            <button type="button" id="btn-use-current-location" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-[#2E7D32] bg-[#66BB6A]/15 hover:bg-[#66BB6A]/25 transition-colors">
                                <i data-lucide="map-pin" class="w-4 h-4"></i>
                                Use current location
                            </button>
                        </div>
                        <button type="submit" class="{{ $btnPrimary }}">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Edit Farm Information
                        </button>
                    </form>
                </div>

                <div class="ag-card mb-5 p-5 sm:p-6">
                    <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#FFCA28]/20 shrink-0">
                            <i data-lucide="bell" class="w-5 h-5 text-[#F9A825]"></i>
                        </span>
                        Notification Settings
                    </h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-3">
                                <i data-lucide="cloud-rain" class="w-5 h-5 text-[#2E7D32]"></i>
                                <span class="text-sm font-medium text-slate-800">Rain Alerts</span>
                            </div>
                            <button type="button" class="ag-toggle active" data-pref="rain" aria-pressed="true" role="switch" title="Toggle rain alerts">
                                <span class="ag-toggle-dot block"></span>
                            </button>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-3">
                                <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600"></i>
                                <span class="text-sm font-medium text-slate-800">Heavy Rain Warnings</span>
                            </div>
                            <button type="button" class="ag-toggle active" data-pref="heavy" aria-pressed="true" role="switch">
                                <span class="ag-toggle-dot block"></span>
                            </button>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-3">
                                <i data-lucide="waves" class="w-5 h-5 text-red-600"></i>
                                <span class="text-sm font-medium text-slate-800">Flood Risk Alerts</span>
                            </div>
                            <button type="button" class="ag-toggle active" data-pref="flood" aria-pressed="true" role="switch">
                                <span class="ag-toggle-dot block"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="ag-card mb-5 p-5 sm:p-6">
                    <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#66BB6A]/20 shrink-0">
                            <i data-lucide="thermometer" class="w-5 h-5 text-[#2E7D32]"></i>
                        </span>
                        Weather Preferences
                    </h2>
                    <div class="space-y-5">
                        <div>
                            <p class="text-sm font-semibold text-slate-700 mb-2">Temperature Unit</p>
                            <div class="flex gap-3">
                                <label class="flex-1 flex items-center gap-2 p-3 rounded-xl border-2 border-[#2E7D32] bg-[#2E7D32]/5 cursor-pointer">
                                    <input type="radio" name="temp_unit" value="C" class="sr-only" checked>
                                    <span class="text-sm font-medium text-slate-800">Celsius (°C)</span>
                                </label>
                                <label class="flex-1 flex items-center gap-2 p-3 rounded-xl border-2 border-slate-200 hover:border-slate-300 cursor-pointer">
                                    <input type="radio" name="temp_unit" value="F" class="sr-only">
                                    <span class="text-sm font-medium text-slate-800">Fahrenheit (°F)</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-700 mb-2">Rainfall Unit</p>
                            <div class="flex gap-3">
                                <label class="flex-1 flex items-center gap-2 p-3 rounded-xl border-2 border-[#2E7D32] bg-[#2E7D32]/5 cursor-pointer">
                                    <input type="radio" name="rain_unit" value="mm" class="sr-only" checked>
                                    <span class="text-sm font-medium text-slate-800">mm</span>
                                </label>
                                <label class="flex-1 flex items-center gap-2 p-3 rounded-xl border-2 border-slate-200 hover:border-slate-300 cursor-pointer">
                                    <input type="radio" name="rain_unit" value="in" class="sr-only">
                                    <span class="text-sm font-medium text-slate-800">inches</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ag-card p-5 sm:p-6">
                    <h2 class="text-base font-bold text-slate-900 mb-4">Account Actions</h2>
                    <div class="space-y-3">
                        <button type="button" onclick="document.getElementById('password-form').classList.remove('hidden'); document.getElementById('password-form').scrollIntoView({behavior:'smooth'})" class="w-full flex items-center gap-3 p-4 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition-colors text-left">
                            <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 shrink-0">
                                <i data-lucide="lock" class="w-5 h-5 text-slate-600"></i>
                            </span>
                            <span class="font-medium text-slate-800">Change Password</span>
                        </button>
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

@push('scripts')
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
@endpush
