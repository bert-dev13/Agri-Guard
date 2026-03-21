# `resources/` — folder structure and file reference

This document maps **every file** under `resources/` in AGRIGUARD: layout, purpose, and how assets compile.

---

## Overview

| Area | Path | Role |
|------|------|------|
| **Vite CSS entry** | `resources/css/app.css` | Tailwind v4 entry, `@source` for Blade, `@import` of all feature CSS |
| **Vite JS entry** | `resources/js/app.js` | Single JS bundle; imports `bootstrap` + public/auth/user modules |
| **Public CSS/JS** | `resources/css/public/`, `resources/js/public/` | Landing, navbar, footer |
| **Auth CSS/JS** | `resources/css/auth/`, `resources/js/auth/` | Login, register, verify-email, forgot-password |
| **User CSS/JS** | `resources/css/user/`, `resources/js/user/` | User navbar, weather, rainfall trends, settings |
| **Blade** | `resources/views/**` | Layouts, components, `public/`, `auth/`, `user/` pages |

**Compile pipeline:** `vite.config.js` inputs `resources/css/app.css` and `resources/js/app.js` only. Output: `public/build/`.

---

## Directory tree

```
resources/
├── css/
│   ├── app.css
│   ├── public/
│   │   ├── landing.css
│   │   ├── navbar.css
│   │   └── footer.css
│   ├── auth/
│   │   ├── login.css
│   │   ├── register.css
│   │   ├── verify-email.css
│   │   └── forgot-password.css
│   └── user/
│       ├── user-navbar.css
│       ├── weather.css
│       ├── rainfall-trends.css
│       └── settings.css
├── js/
│   ├── app.js
│   ├── bootstrap.js
│   ├── public/
│   │   ├── landing.js
│   │   └── navbar.js
│   ├── auth/
│   │   ├── login.js
│   │   ├── register.js
│   │   ├── verify-email.js
│   │   └── forgot-password.js
│   └── user/
│       ├── user-navbar.js
│       ├── weather.js
│       ├── rainfall-trends.js
│       └── settings.js
└── views/
    ├── layouts/
    │   ├── public.blade.php
    │   ├── auth.blade.php
    │   └── user.blade.php
    ├── components/
    │   ├── public-navbar.blade.php
    │   ├── user-navbar.blade.php
    │   └── footer.blade.php
    ├── public/
    │   └── landing.blade.php
    ├── auth/
    │   ├── login.blade.php
    │   ├── register.blade.php
    │   ├── verify-email.blade.php
    │   ├── forgot-password.blade.php
    │   └── email-verification-code.blade.php
    └── user/
        ├── weather/
        │   └── weather-details.blade.php
        ├── rainfall/
        │   └── rainfall-trends.blade.php
        └── settings/
            └── settings.blade.php
```

---

## Layouts and components

| View | Role |
|------|------|
| `layouts.public` | `@vite`, `<x-public-navbar />`, main slot, `<x-footer />` |
| `layouts.auth` | Same Vite; default shell = public navbar + main + footer; optional `@section('auth-shell')` for verify-email |
| `layouts.user` | Vite, `<x-user-navbar />`, main slot, `@stack('scripts')` |
| `components.public-navbar` | Guest marketing / auth header |
| `components.user-navbar` | Authenticated app header |
| `components.footer` | Site footer |

---

## CSS / JS import graph

**`app.css`** imports `./public/*`, `./auth/*`, `./user/*` (see file for full list).

**`app.js`** imports `./bootstrap`, then `./public/navbar`, `./public/landing`, `./auth/*`, `./user/*`.

---

## Related files (outside `resources/`)

| File | Relation |
|------|----------|
| `routes/web.php` | `view('public.landing')`, auth/user routes |
| `RainfallTrendsController` | `view('user.rainfall.rainfall-trends')` |
| `EmailVerificationCodeMail` | `view('auth.email-verification-code')` |
| `vite.config.js` | Vite inputs |

---

*Update this document when you add new Blade or asset modules.*
