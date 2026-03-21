@extends('layouts.public')

@section('title', 'AGRIGUARD – Smart Weather Insights for Safer Farming')

@section('body-class', 'min-h-screen flex flex-col bg-[#F8FAFC]')

@section('content')
    {{-- Hero — bg #F8FAFC --}}
    <section id="home" class="landing-section-hero relative min-h-[90vh] flex items-center pt-24 pb-28 lg:pt-32 lg:pb-36 overflow-hidden bg-[#F8FAFC]">
        <div class="absolute inset-0 pointer-events-none bg-[#F8FAFC]">
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat opacity-90 hero-bg-image" style="background-image: url('{{ asset('images/background-image.png') }}');"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-[#F8FAFC] via-[#F8FAFC]/85 to-[#F8FAFC]/40 pointer-events-none"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-[#00809D]/[0.04] via-transparent to-[#10B981]/[0.04] pointer-events-none"></div>
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-[#00809D]/[0.05] rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-[#10B981]/[0.05] rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_1.6fr] gap-10 lg:gap-14 xl:gap-16 items-center min-h-0">
                <div class="order-2 lg:order-1 max-w-xl">
                    <div class="hero-headline hero-brand mb-6 lg:mb-8">
                        <div class="hero-brand-logo">
                            <img src="{{ asset('images/agriguard-logo.png') }}" alt="AGRIGUARD" class="hero-brand-logo-img" />
                        </div>
                        <span class="hero-brand-wordmark text-[#00809D]">AGRIGUARD</span>
                    </div>
                    <h1 class="hero-subheadline text-4xl sm:text-5xl lg:text-6xl font-bold text-slate-900 leading-[1.1] tracking-tight mt-2">
                        Smart Weather Insights for <span class="text-[#00809D]">Safer Farming</span>
                    </h1>
                    <p class="hero-description mt-6 text-lg text-slate-600 leading-relaxed">
                        A decision support system that helps farmers prepare for heavy rain and flood risks using real-time weather insights.
                    </p>
                    <div class="hero-buttons mt-10 flex flex-wrap gap-4">
                        <a href="{{ url('/register') }}" class="inline-flex items-center justify-center px-7 py-3.5 rounded-xl bg-[#00809D] text-white font-semibold shadow-lg shadow-[#00809D]/25 hover:bg-[#00809D]/90 hover:shadow-xl hover:shadow-[#00809D]/30 hover:-translate-y-0.5 transition-all duration-300">
                            Get Started
                            <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                        </a>
                        <a href="#how-it-works" class="inline-flex items-center justify-center px-7 py-3.5 rounded-xl bg-transparent border-2 border-slate-300 text-slate-700 font-semibold hover:border-[#00809D]/50 hover:text-[#00809D] transition-all duration-300">
                            Learn More
                        </a>
                    </div>
                </div>
                <div class="order-1 lg:order-2 flex items-center justify-center lg:justify-end min-w-0">
                    <div class="hero-image-focal hero-image-reveal">
                        <img src="{{ asset('images/hero_image.png') }}" alt="Smart Weather Insights for Safer Farming" class="hero-image-main" />
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- About --}}
    <section id="about" class="landing-section py-24 lg:py-32 bg-[#F8FAFC] relative border-t border-slate-100/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto scroll-reveal mb-14 lg:mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900">See AGRIGUARD in Action</h2>
                <p class="mt-4 text-lg text-slate-600">Dashboard, weather results, and advisories in one place.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="scroll-reveal space-y-6 order-2 lg:order-1">
                    <p class="text-slate-600 leading-relaxed">
                        AGRIGUARD brings weather data, flood risk, and preparedness advice into one simple interface. View forecasts, check risk levels, and get actionable advisories tailored to your farm.
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-[#00809D]/10 text-[#00809D] shrink-0">
                                <i data-lucide="cloud-rain" class="w-5 h-5"></i>
                            </span>
                            <div>
                                <span class="font-semibold text-slate-900">Weather &amp; risk at a glance</span>
                                <p class="text-sm text-slate-600 mt-0.5">Rainfall forecast and flood risk for your area in one view.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-[#10B981]/10 text-[#10B981] shrink-0">
                                <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                            </span>
                            <div>
                                <span class="font-semibold text-slate-900">Clear advisories</span>
                                <p class="text-sm text-slate-600 mt-0.5">Actionable recommendations when conditions change.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-[#00809D]/10 text-[#00809D] shrink-0">
                                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                            </span>
                            <div>
                                <span class="font-semibold text-slate-900">One dashboard</span>
                                <p class="text-sm text-slate-600 mt-0.5">Dashboard, Weather, My Farms, and Advisories in a single app.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="scroll-reveal order-1 lg:order-2">
                    <div class="dashboard-preview rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50 overflow-hidden hover:shadow-2xl hover:shadow-[#00809D]/10 transition-all duration-300">
                        <div class="flex items-center gap-1 px-4 py-3 bg-slate-50 border-b border-slate-200">
                            <span class="px-3 py-1.5 rounded-lg bg-[#00809D] text-white text-xs font-medium">Dashboard</span>
                            <span class="px-3 py-1.5 rounded-lg text-slate-500 text-xs font-medium">Weather</span>
                            <span class="px-3 py-1.5 rounded-lg text-slate-500 text-xs font-medium">My Farms</span>
                            <span class="px-3 py-1.5 rounded-lg text-slate-500 text-xs font-medium">Advisories</span>
                        </div>
                        <div class="p-5 space-y-4 min-h-[280px]">
                            <div class="rounded-xl border border-slate-200 bg-white p-4 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="flex items-center justify-center w-11 h-11 rounded-lg bg-[#00809D]/10 text-[#00809D] shrink-0">
                                        <i data-lucide="cloud-rain" class="w-5 h-5"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900 text-sm">Rainfall Forecast</p>
                                        <p class="text-xs text-slate-500 mt-0.5">Next 48 hours · Moderate risk</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-medium shrink-0">Advisory active</span>
                            </div>
                            <div class="rounded-xl border border-[#10B981]/30 bg-[#10B981]/5 p-4">
                                <p class="text-xs font-semibold text-[#10B981] uppercase tracking-wide">Advisory Active</p>
                                <p class="text-sm font-medium text-slate-900 mt-1">Preparedness advisory</p>
                                <p class="text-xs text-slate-600 mt-2 leading-relaxed">Consider draining excess water from low-lying areas. Secure stored harvest. Monitor livestock locations.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- How It Works — redesigned --}}
    <section id="how-it-works" class="landing-section how-it-works py-24 lg:py-32 bg-[#F1F7F9] relative border-t border-[#00809D]/5 overflow-x-clip">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <header class="how-it-works__header text-center max-w-2xl mx-auto mb-14 lg:mb-16 scroll-reveal">
                <h2 class="how-it-works__title text-3xl sm:text-4xl font-bold text-slate-900">How It Works</h2>
                <p class="how-it-works__subtitle mt-4 text-lg text-slate-600">Four simple steps from sign-up to smart advisories.</p>
            </header>

            <ol class="how-it-works__grid relative z-10">
                @foreach([
                    ['num' => '01', 'icon' => 'user-plus', 'title' => 'Register Account', 'desc' => 'Create your account in minutes. No complex setup.'],
                    ['num' => '02', 'icon' => 'map-pinned', 'title' => 'Enter Farm Details', 'desc' => 'Add your farm location and key details for accurate risk analysis.'],
                    ['num' => '03', 'icon' => 'cloud-sun', 'title' => 'Analyze Weather & Flood Risk', 'desc' => 'We process weather data and classify flood risk for your area.'],
                    ['num' => '04', 'icon' => 'bell', 'title' => 'Receive Smart Advisories', 'desc' => 'Get clear, actionable preparedness advice when you need it.'],
                ] as $step)
                    <li class="scroll-reveal">
                        <article class="how-step-card h-full">
                            <div class="how-step-card__meta">
                                <span class="how-step-card__number">{{ $step['num'] }}</span>
                                <span class="how-step-card__icon" aria-hidden="true">
                                    <i data-lucide="{{ $step['icon'] }}" class="w-6 h-6"></i>
                                </span>
                            </div>
                            <h3 class="how-step-card__title">{{ $step['title'] }}</h3>
                            <p class="how-step-card__desc">{{ $step['desc'] }}</p>
                        </article>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="landing-section py-24 lg:py-32 bg-white border-t border-slate-100/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16 lg:mb-20 scroll-reveal">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900">Features</h2>
                <p class="mt-4 text-lg text-slate-600">Everything you need to stay ahead of rain and flood risks.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                @foreach([
                    ['icon' => 'cloud', 'title' => 'Real-Time Weather Monitoring', 'desc' => 'Live rainfall and storm tracking for your area.'],
                    ['icon' => 'cloud-rain', 'title' => 'Rainfall Forecast Analysis', 'desc' => 'See expected rainfall and intensity ahead of time.'],
                    ['icon' => 'alert-triangle', 'title' => 'Flood Risk Classification', 'desc' => 'Clear risk levels so you know when to take action.'],
                    ['icon' => 'leaf', 'title' => 'Farm-Specific Preparedness Advice', 'desc' => 'Recommendations tailored to your farm and crops.'],
                    ['icon' => 'layout-dashboard', 'title' => 'Simple Farmer Dashboard', 'desc' => 'One place for weather, risk, and advisories.'],
                    ['icon' => 'users', 'title' => 'Community Agriculture Support', 'desc' => 'Support for cooperatives and local agriculture offices.'],
                ] as $feature)
                    <div class="scroll-reveal features-card group h-full">
                        <div class="h-full flex flex-col p-6 lg:p-8 rounded-2xl bg-[#F8FAFC] border border-slate-100 shadow-sm hover:shadow-xl hover:shadow-[#00809D]/8 hover:-translate-y-1.5 hover:border-[#00809D]/20 transition-all duration-300">
                            <span class="flex items-center justify-center w-14 h-14 rounded-xl bg-[#00809D]/10 text-[#00809D] shrink-0 group-hover:bg-[#00809D]/15 transition-colors duration-300">
                                <i data-lucide="{{ $feature['icon'] }}" class="w-7 h-7"></i>
                            </span>
                            <h3 class="mt-5 text-lg font-semibold text-slate-900 mb-2">{{ $feature['title'] }}</h3>
                            <p class="text-sm text-slate-600 leading-relaxed mt-auto">{{ $feature['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection

@push('head')
    <style>
        .how-it-works__grid {
            --how-card-radius: 1rem;
            --how-card-border: rgba(148, 163, 184, 0.22);
            --how-card-shadow: 0 12px 30px rgba(2, 44, 34, 0.08);
            --how-card-shadow-hover: 0 18px 38px rgba(0, 128, 157, 0.16);
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .how-step-card {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 0.95rem;
            height: 100%;
            padding: 1.35rem;
            background: #ffffff;
            border: 1px solid var(--how-card-border);
            border-radius: var(--how-card-radius);
            box-shadow: var(--how-card-shadow);
            transition: transform 220ms ease, border-color 220ms ease, box-shadow 220ms ease;
        }

        .how-step-card:hover {
            transform: translateY(-4px);
            border-color: rgba(0, 128, 157, 0.35);
            box-shadow: var(--how-card-shadow-hover);
        }

        .how-step-card__meta {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            min-height: 2.25rem;
        }

        .how-step-card__number {
            font-size: 1.45rem;
            line-height: 1;
            letter-spacing: -0.01em;
            font-weight: 800;
            color: #0f766e;
        }

        .how-step-card__icon {
            width: 2.25rem;
            height: 2.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.7rem;
            background: rgba(16, 185, 129, 0.12);
            color: #0f766e;
            flex-shrink: 0;
        }

        .how-step-card__title {
            margin: 0;
            font-size: 1.07rem;
            line-height: 1.35;
            font-weight: 700;
            color: #0f172a;
        }

        .how-step-card__desc {
            margin: 0;
            font-size: 0.94rem;
            line-height: 1.58;
            color: #475569;
        }

        @media (min-width: 640px) {
            .how-step-card {
                padding: 1.5rem;
            }
        }

        @media (min-width: 768px) {
            .how-it-works__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .how-it-works__grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 1.5rem;
            }

            .how-it-works__grid::before {
                content: "";
                position: absolute;
                left: 3%;
                right: 3%;
                top: 2.55rem;
                height: 2px;
                border-radius: 999px;
                background: linear-gradient(90deg, rgba(0, 128, 157, 0.14), rgba(16, 185, 129, 0.4), rgba(0, 128, 157, 0.14));
                pointer-events: none;
                z-index: 0;
            }

            .how-it-works__grid > li {
                position: relative;
                z-index: 1;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>document.addEventListener('DOMContentLoaded', function() { if (typeof lucide !== 'undefined') lucide.createIcons(); });</script>
@endpush
