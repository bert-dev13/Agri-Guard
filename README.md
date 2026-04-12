# AGRIGUARD

Web application for **rice and corn farmers** in **Amulung, Cagayan**, combining **local weather data**, **rainfall context**, **map-based farm GPS context**, and **AI-assisted guidance** for recommendations inside a **mobile-style** farmer dashboard.

---

## Tech stack

| Layer | Stack |
|--------|--------|
| Backend | **Laravel 12**, **PHP 8.2+** |
| Frontend | **Vite 7**, **Tailwind CSS 4**, **Blade** templates |
| Database | **MySQL** (default; configurable) |
| Auth | Session-based login, **email OTP verification** before app access |

---

## Current pages & features

## Latest upgrades and improvements

- Added a dedicated **Farm Map** flow (`/map`) so users can set and persist farm GPS coordinates used by weather and advisory context.
- Expanded **Crop Progress** with adaptive timeline logic and richer stage handling backed by new crop-state fields and timeline services.
- Upgraded **Weather** and **Rainfall** experiences with improved context handling and backend support for rainfall heatmap-style data processing.
- Improved **Dashboard** and shared user pages for tighter visual consistency, cleaner recommendation presentation, and better quick-action flow.
- Enhanced **Settings** farm profile handling to support newer farm/crop state fields used across weather and crop progress modules.
- Removed the separate **AI Assistant chat page** and related routes/assets to simplify navigation and keep guidance embedded in core pages.

### Public (guests)

| Path | Description |
|------|-------------|
| `/` | **Landing** — marketing entry; logged-in users are redirected to the dashboard |
| `/login` | Sign in |
| `/register` | Sign up (farm municipality **Amulung**, barangay from PSGC-backed list, optional crop/stage/planting date/area) |
| `/verify-email` | **Email verification** — 6-digit OTP sent after registration or when login detects an unverified account |
| `/forgot-password` | Placeholder view (future work) |

### Authenticated app (requires login **and** verified email)

All routes below use `auth` + `verified.email` middleware.

| Path | Name | Description |
|------|------|-------------|
| `/dashboard` | `dashboard` | **Home** — greeting, **farm summary** (farm, crop, stage, location), **smart recommendation** card, **current weather** + **5-day forecast**, **quick actions** (Weather, Rainfall, Farm profile anchor, Settings) |
| `/weather` | `weather-details` | **Weather details** — hero aligned with dashboard, current conditions, rain/humidity/wind, **today’s smart weather** guidance, clay-style icon set |
| `/weather/rainfall` | `rainfall-trends` | **Rainfall trends** — charts/context from advisory data + related insights |
| `/crop-progress` | `crop-progress.index` | **Crop progress** — summary header, **key details** grid (farm, crop, stage, planted date), **growth timeline** with progress bar and stage cards, **next stage** highlight, **Smart Advice** (summary, what to do/watch/avoid, why it matters, risk badge); stage-based AI uses weather context (**Together AI** with fallback copy if unavailable) |
| `/map` | `map.index` | **Farm map** — map-based GPS pin/save flow for farm coordinates and location context used by downstream weather/advisory features |
| `/settings` | `settings` | **Settings** — account, password, **farm profile** (location, crop, **farming stage**, planting date, area, coordinates as supported) |

**Navigation (main bar):** Dashboard, Weather, Crop Progress, Map. **Settings** and **Logout** are in the user menu (and Settings in the mobile drawer).

**Legacy redirects (301):** `/weather-details` → `/weather`, `/rainfall-trends` → `/weather/rainfall`. `/farm-profile` → `/settings`.

### HTTP API (authenticated)

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/weather` | Weather payload for the logged-in user’s farm context |
| `GET` | `/api/weather/by-coordinates` | Weather by coordinates (where used) |
| `POST` | `/api/map/save-gps-location` | Persist user farm GPS location from the map flow |
| `GET` | `/api/map/farm-context` | Return map/farm context payload for map-enabled features |
| `POST` | `/logout` | End session |

### Other server routes

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/amulung-barangays` | **PSGC proxy** — barangay list for Amulung (registration) |
| `PUT` | `/crop-progress/stage` | `crop-progress.update-stage` — update growth stage / planting date (available for forms or clients; primary farm edits are also in **Settings**) |
| `POST` | `/crop-progress/reality-check` | `crop-progress.reality-check` — record reality check state for crop stage workflow |
| `POST` | `/crop-progress/reality-check/reopen` | `crop-progress.reality-check-reopen` — reopen the reality check flow when needed |
| `PUT` | `/crop-progress/current-stage` | `crop-progress.update-current-stage` — persist current stage updates from enhanced crop timeline UX |

---

## Key backend services

| Service | Role |
|---------|------|
| `WeatherAdvisoryService` | Builds **advisory data** for the user: current weather, forecast, charts (e.g. monthly trend), rain probability display, location copy |
| `TogetherAiService` | Calls **Together** chat/completions API for JSON-style recommendations |
| `FarmRecommendationService` | Builds AI payloads and normalizes **smart recommendations** for dashboard/weather-style UIs |
| `AiRecommendationService` | Orchestrates farm recommendations + **success/fallback metadata** for the dashboard smart card |
| `CropProgressController` | **Stage timeline** + **stage-based smart advice** (AI + fallback timeline from planting date and weather heuristics) |
| `RainfallTrendsController` | Rainfall trends page data + supporting AI/context |
| `CropTimelineService` | Computes timeline/stage progression logic for enhanced crop progress workflows |
| `RainfallHeatmapService` | Aggregates/normalizes rainfall patterns for map/chart-ready rainfall context |
| `FarmMapController` | Handles farm map page delivery and GPS/context API endpoints |
| `RuleBasedAdvisoryService` | **Rule-only** advisory engine (documented in `docs/`; used where rule-based logic applies) |

---

## Frontend / UX notes (current)

- **User shell:** shared **navbar** (`resources/views/components/user-navbar.blade.php`), layouts under `resources/views/layouts/`, and a dedicated **Map** page for farm location workflows.
- **Dashboard, Weather, Crop Progress** share a **consistent card system** (e.g. `ag-card`, welcome gradient, soft shadows) and **Inter** where configured on those views.
- **Weather details** and **Crop Progress** use **clay-style inline SVG icons** (data URIs) for a cohesive 3D-like look on key stats and sections.
- **Crop Progress — Smart Advice:** four blocks (**What to do**, **What to watch**, **What to avoid**, **Why this matters**) in a **2×2 grid** from the `sm` breakpoint (~640px); single column on smaller screens.
- Assets: `npm run dev` / `npm run build` via **Vite** (`vite.config.js`); styles in `resources/css/` (including `user/dashboard.css`, `user/weather.css`, `user/crop-progress.css`, `user/user-navbar.css`).

---

## `resources/` folder structure (current)

```
resources/
├── css/
│   ├── app.css
│   ├── auth/
│   │   ├── forgot-password.css
│   │   ├── login.css
│   │   ├── register.css
│   │   └── verify-email.css
│   ├── public/
│   │   ├── footer.css
│   │   ├── landing.css
│   │   └── navbar.css
│   └── user/
│       ├── assistant.css
│       ├── crop-progress.css
│       ├── dashboard.css
│       ├── farm-map.css
│       ├── rainfall-trends.css
│       ├── settings.css
│       ├── user-navbar.css
│       └── weather.css
├── js/
│   ├── app.js
│   ├── bootstrap.js
│   ├── auth/
│   │   ├── forgot-password.js
│   │   ├── login.js
│   │   ├── register.js
│   │   └── verify-email.js
│   ├── public/
│   │   ├── landing.js
│   │   └── navbar.js
│   └── user/
│       ├── assistant.js
│       ├── assistant/
│       │   ├── suggestionEngine.js
│       │   ├── topicClassifier.js
│       │   └── topicLibrary.js
│       ├── crop-progress.js
│       ├── dashboard.js
│       ├── farm-map.js
│       ├── rainfall-trends.js
│       ├── settings.js
│       ├── user-navbar.js
│       └── weather.js
└── views/
    ├── auth/
    │   ├── email-verification-code.blade.php
    │   ├── forgot-password.blade.php
    │   ├── login.blade.php
    │   ├── register.blade.php
    │   └── verify-email.blade.php
    ├── components/
    │   ├── footer.blade.php
    │   ├── public-navbar.blade.php
    │   ├── smart-recommendation.blade.php
    │   └── user-navbar.blade.php
    ├── layouts/
    │   ├── auth.blade.php
    │   ├── public.blade.php
    │   └── user.blade.php
    ├── public/
    │   └── landing.blade.php
    └── user/
        ├── assistant/
        │   └── index.blade.php
        ├── crop-progress/
        │   └── index.blade.php
        ├── dashboard.blade.php
        ├── map/
        │   └── index.blade.php
        ├── rainfall/
        │   └── rainfall-trends.blade.php
        ├── settings/
        │   └── settings.blade.php
        └── weather/
            └── weather-details.blade.php
```

---

## Documentation in this repo

| File | Content |
|------|---------|
| `docs/ADVISORY_ENGINE.md` | Rule-based advisory engine (inputs/outputs) |
| `docs/RESOURCES_STRUCTURE.md` | Resources / structure notes |

---

## Configuration (environment)

Typical keys (see `config/services.php` and `config/togetherai.php`):

- **`OPENWEATHERMAP_API_KEY`** — OpenWeatherMap integration  
- **`TOGETHER_API_KEY`**, **`TOGETHER_MODEL`**, **`TOGETHER_BASE_URL`**, **`TOGETHER_FALLBACK_MODELS`** — Together AI  

Database, mail, session, and app settings follow Laravel’s usual `.env` layout (see `.env.example`).

---

## Quick start (development)

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure .env (database, mail, API keys)
php artisan migrate
npm install
npm run dev
```

Or run the full Composer setup script (installs PHP deps, creates `.env` if missing, generates app key, migrates, runs `npm install` and `npm run build`):

```bash
composer run setup
```

**Admin user exports (PDF, Excel, print):** Dependencies are declared in `composer.json` (`barryvdh/laravel-dompdf`, `maatwebsite/excel`) and locked in `composer.lock`. PHP extensions required for **PhpSpreadsheet** / XLSX (e.g. `zip`, `xml`, `gd`, `dom`) are listed under `require` so `composer install` validates your PHP build—on Linux, install packages such as `php-zip`, `php-xml`, and `php-gd` if Composer reports missing extensions. Published config is committed at `config/excel.php` and `config/dompdf.php`, so you do **not** need to run `php artisan vendor:publish` on a new clone.

Run the app with `php artisan serve` (or your preferred stack). Ensure **migrations** are applied and **mail** is configured (or `MAIL_MAILER=log`) for OTP verification.

---

## License

Project inherits Laravel’s default **MIT** license unless otherwise specified by the repository owner.
