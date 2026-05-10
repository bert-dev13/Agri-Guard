# AgriGuard production optimization report

**Date:** 2026-05-10  
**Scope:** Laravel 12 application, OpenWeatherMap integrations, Together AI advisories, Python XGBoost/Sklearn predictor, MySQL indexes, Vite production build, Composer production install.

---

## 1. Files removed or archived

| Action | Path / notes |
|--------|----------------|
| Deleted (generated) | `python/__pycache__/` — bytecode cache; stays excluded via `.gitignore`. Not removed: trained models (`python/model/*.pkl`, `*.meta.json`), `python/predict.py`, `python/verify_model_load.py`, `python/requirements.txt`. |
| Not bulk-deleted | No speculative deletes under `public/` or `resources/` — avoids breaking Blade/Vite references. Legacy items remain under `deprecated/` per project convention. |

---

## 2. Dependencies

### Composer

- **Executed:** `composer install --no-dev --optimize-autoloader`.
- **Removed from vendor (dev-only):** Pest, PHPUnit, Collision, Sail, Pint, Pail, Faker, Mockery, and transitive dev packages (~50 packages).
- **Warning:** Composer reported the lock file may be out of date vs `composer.json`. For reproducible deploys, run `composer update` only when intentionally bumping dependencies, then commit `composer.lock`.

**Local development:** After this optimization, restore dev tools with:

```bash
composer install
```

### NPM

- **Executed:** `npm run build` (Vite production bundle to `public/build/`).

### Python (`python/requirements.txt`)

- **Removed:** `pandas` from inference hot path (was only used to build a one-row frame).
- **Added / relied on:** `numpy>=1.24` for feature matrices passed to `model.predict()`.
- **Kept:** `joblib`, `scikit-learn`, `xgboost`, production model artifacts under `python/model/`.

**Deploy note:** Run `pip install -r python/requirements.txt` inside the production venv after pull.

---

## 3. Database improvements

### Migration: `2026_05_10_120000_add_production_performance_indexes`

| Table | Index | Purpose |
|-------|--------|---------|
| `barangays` | `barangays_municipality_index` on `municipality` | Faster `WHERE municipality = ?` (farm map flood listings). |
| `users` | `users_farm_municipality_index` on `farm_municipality` | Admin user filters and municipality-scoped queries. |
| `historical_weather` | `historical_weather_year_index` on `year` | Year-range aggregates for rainfall analytics and flood stress. |
| `historical_weather` | `historical_weather_year_rainfall_index` on `(year, rainfall)` | Supports filtered counts and thresholds. |

**Applied locally:** `php artisan migrate --force` completed successfully.

### Query behavior

- **Barangay flood overview:** Replaced two sequential `COUNT()` queries with one aggregate using `COUNT(*)` and `SUM(CASE WHEN …)` over the same filtered set.
- **Historical CSV import:** Clears `HistoricalWeather` static rainfall-unit cache and Laravel cache key `barangay_flood_hist_agg:v1` so bulk imports do not leave stale derived data in long-lived PHP workers.

---

## 4. Performance optimizations (application)

### Caching

| Feature | Mechanism |
|---------|-----------|
| Weather (OpenWeatherMap) | Existing per-user and per-coordinate TTL caches in `FarmWeatherService` (unchanged TTL: 15 minutes). |
| ML weather prediction | Existing `Cache::remember` in `WeatherPredictionService` (config `AGRIWEATHER_PREDICT_CACHE_MINUTES`). |
| Flood barangay tiers | **New:** Full municipality overview cached (`barangay_flood_overview:v2:*`) with TTL from `BARANGAY_FLOOD_OVERVIEW_CACHE_MINUTES` / `config('barangay_flood_risk.cache_ttl_minutes')`. |
| Historical stress aggregate | **New:** `barangay_flood_hist_agg:v1` shared TTL for heavy-rain ratio computation. |
| AI Smart Advisory | Existing fingerprinted cache in `AiAdvisoryService`; routine logs demoted from `info` to `debug` to reduce I/O in production when `LOG_LEVEL=info`. |

### HTTP / API

- **OpenWeatherMap:** `FarmWeatherService` now (1) reuses cached geocode via `getCoordinatesForUser()` before repeating a geocode call, and (2) loads **current** and **5-day forecast** in parallel via `Http::pool()` to reduce wall-clock latency.

### Eloquent / N+1

- **User ↔ Barangay:** `User::loadMissing('barangay')` on farmer dashboard and farm map flows; accessors prefer the loaded relation before hitting `Barangay::nameForId()` / `find()`.

### Pagination

- Admin user list already used `paginate(15)` — no change required.

---

## 5. Laravel caches generated

Commands run successfully:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

**Note:** Route caching is environment-sensitive (`/dev/test-mail` is only registered when `app()->environment('local')`). Build caches on the target environment (production `APP_ENV=production`).

---

## 6. Python model optimizations

- **Inference path:** `python/predict.py` now builds `numpy` `(1, n_features)` arrays instead of `pandas.DataFrame` per day — lower overhead per subprocess invocation.
- **Process model:** PHP still invokes Python per cache miss; prediction remains bounded by `AGRIWEATHER_PREDICT_CACHE_MINUTES` and subprocess startup. Further gains would require a long-lived inference worker (out of scope here).

---

## 7. `.env` / configuration for production

`.env.example` was updated with comments (not committed secrets) for:

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`
- `LOG_LEVEL=warning` (recommended)
- `BARANGAY_FLOOD_OVERVIEW_CACHE_MINUTES`
- Existing ML and OpenWeather variables

**Operators must copy `.env.example` → `.env` on the server and set real secrets; never commit `.env`.

---

## 8. Background queues (deferred)

Heavy admin PDF/XLSX exports and notifications were **not** converted to queued jobs in this pass — that would touch export controllers, storage of temp files, and UX for async downloads. **Recommendation:** introduce queued exports when traffic or timeouts justify it, using `QUEUE_CONNECTION` already set to `database` in `.env.example`.

---

## 9. Potential issues or warnings

1. **Composer lock drift:** Resolve with `composer update` when bumping PHP or Laravel packages; commit an updated `composer.lock`.
2. **Developer installs:** Production `--no-dev` removes test runners; CI and local dev need full `composer install`.
3. **Flood overview cache invalidation:** Municipality overview keys include config file mtime; after changing `config/barangay_flood_risk.php`, caches refresh naturally. After **historical weather** changes, import clears `barangay_flood_hist_agg:v1`; full municipality keys may still exist until TTL — use `php artisan cache:clear` if you need an immediate full reset.
4. **Static assets:** `public/build` is gitignored; deployment must run `npm ci && npm run build` (or ship artifacts from CI).
5. **ML naming:** Product copy may refer to “ANN / MLP”; the deployed predictor is the **joblib** model at `python/model/xgboost_weather_model.pkl` invoked by `predict.py` (architecture is defined at training time).

---

## 10. Recommendations for future improvements

- **Cache backend:** For multi-instance deployments, prefer **Redis** for `CACHE_STORE` and `SESSION_DRIVER` instead of `database` to reduce DB contention.
- **Horizon / queues:** Use Laravel Horizon with Redis for predictable worker scaling if advisory or export jobs move to the queue.
- **Image pipeline:** Run `vite build` with compressed assets; audit large PNGs under `public/` only when replacing branding tiles.
- **Observability:** Set `LOG_LEVEL=warning` or `error` in production; keep structured logging for Together AI and weather failures only at `warning`/`error`.
- **Inference service:** If Python cold-start becomes a bottleneck, add a small HTTP sidecar that loads the model once and exposes `/predict`.

---

## 11. Verification checklist (manual)

| Area | Suggested check |
|------|-------------------|
| Auth / roles | Login as farmer and admin; confirm redirects. |
| Farm management | Update farm settings and GPS; confirm map context updates. |
| Weather / ML | Hit `/api/weather-prediction` and dashboard weather after setting `OPENWEATHERMAP_API_KEY` and Python venv. |
| Flood tiers | Open Farm Map; confirm barangay lists load. |
| AI advisory | Pages using `AiAdvisoryService` when `TOGETHER_API_KEY` is set. |
| Admin | User list sort/filter and pagination. |

---

*End of original report.*

---

# Addendum — Round 2 (2026-05-10)

This round broadens the optimization across **every page** (Landing, Auth, Dashboard, Weather, Map, Crop Monitoring, Analytics, AI Assistant) and pushes harder on caching, frontend payload size, request-scoped memoization, performance observability, and scheduled cache warming.

## A1. Frontend bundle: per-route dynamic imports + vendor chunk splitting

`vite.config.js` now declares `manualChunks` so heavy libs each become their own cacheable chunk:

- `vendor-leaflet` (~149 kB) — only loaded by the Farm Map page.
- `vendor-turf` (~4 kB) — only loaded with farm-map.
- `vendor-axios` (~37 kB) — eager (used app-wide for API calls).

`resources/js/app.js` switched from “import every page module on every page” to a `loadIf(selector, importer)` pattern using `requestIdleCallback` so per-page modules (`dashboard`, `weather`, `weather-details`, `rainfall-trends`, `crop-progress`, `settings`, `assistant`, admin pages) only download when their page-specific marker class is in the DOM, and only after the browser is idle.

`resources/js/bootstrap.js` no longer bundles the `lucide` package (was ~375 kB / 84 kB gzip). All layouts already load Lucide deferred from unpkg with `<script defer>`, exposing `window.lucide` and `window.refreshLucideIcons()`. **Net initial JS dropped to ~50 kB gzipped** (loader 6.4 kB + axios 15 kB).

```
public/build/assets/app-*.js                6.43 kB │ gzip:  2.57 kB
public/build/assets/vendor-axios-*.js      37.57 kB │ gzip: 15.03 kB
public/build/assets/vendor-leaflet-*.js   149.73 kB │ gzip: 43.39 kB   (loaded on /map only)
```

## A2. Landing page optimizations

- Hero `<img>` tags now declare explicit `width` / `height`, `decoding="async"`, and `fetchpriority="high"` to lock layout and prioritize the LCP candidate.
- `resources/views/layouts/public.blade.php` adds `<link rel="preload" as="image" href=".../hero_image.png" fetchpriority="high">` so the hero begins downloading before JS is parsed, plus `<meta name="description">`, `theme-color`, and `dns-prefetch` for the Lucide CDN.
- The landing page Lucide UMD script is now `defer`red and renders icons on `window.load`, never blocking first paint.
- Landing page makes **no API calls on initial render** (verified — `landing.js` only sets up an `IntersectionObserver` for scroll reveal).
- **Branding image sizes flagged for compression:** `public/images/agriguard-logo.png` ≈ 1.5 MB, `hero_image.png` ≈ 2.5 MB, `background-image.png` ≈ 1.4 MB. Recommend running these through `squoosh` or `cwebp` before the next deploy. Even at 70 % quality WebP they should drop below 200 kB each.

## A3. Layout-wide Lucide deferral & request-scoped helpers

`resources/views/layouts/{user,admin}.blade.php` switched to `<script defer>` for the unpkg Lucide bundle and exposed `window.refreshLucideIcons()` so feature modules can re-render icons after dynamic DOM updates without re-downloading the library.

Added `<meta name="csrf-token">` to the user layout (some JS modules depend on it for POST requests), and `dns-prefetch` to admin layout.

## A4. Skeleton loaders for perceived speed

- New shared CSS module `resources/css/common/skeleton.css` provides `.ag-skeleton`, `.ag-skeleton--text`, `.ag-skeleton--block`, `.ag-skeleton--card` with shimmering animation (and `prefers-reduced-motion` opt-out).
- Dashboard ML strip (`Rainfall`, `Wind Speed`, `Status`) uses `data-skeleton` markers and a small inline script that fires inside `requestIdleCallback`, replacing each placeholder when the prediction returns. Fetch timeout reduced from `100s` to `30s` to stop the spinner from outlasting reasonable Python invocation time.

## A5. Static asset cache headers (`public/.htaccess`)

Added `mod_headers` + `mod_expires` + `mod_deflate` blocks:

- Vite-built bundles (`/build/**`) get `Cache-Control: public, max-age=31536000, immutable`.
- Logos, hero images, fonts get 30 days (1 year for woff/woff2).
- Hand-authored CSS/JS get 7 days; static JSON 1 day.
- gzip enabled for HTML, CSS, JS, JSON, SVG, fonts.

**Effect:** repeat visits skip the network for hashed bundles entirely; first-time downloads ship gzipped.

## A6. Request-scoped memoization + singleton bindings

`AppServiceProvider::register()` now binds these as singletons:

- `FarmWeatherService`
- `WeatherAdvisoryService`
- `WeatherPredictionService`
- `BarangayFloodRiskOverviewService`
- `AiAdvisory\AiAdvisoryService`

Each service has an internal `$requestMemo` array, so a single dashboard render that touches `FarmWeatherService` from advisory, AI recommendation, three-day outlook, etc. only resolves the cache once and reuses the deserialized array. Same for `WeatherAdvisoryService::getAdvisoryData()` and `WeatherPredictionService::predict()`.

## A7. New caches added (Laravel `Cache::remember`)

| Cache key prefix | TTL | Owner | Bust |
|------------------|-----|-------|------|
| `wadv:monthly_trend:v1:*` | 6 h | `WeatherAdvisoryService` | Auto via key version + `forgetHistoricalAggregateCaches()` |
| `wadv:yearly_totals:v1:*` | 6 h | `WeatherAdvisoryService` | same |
| `wadv:heavy_rain_stats:v1:*` | 6 h | `WeatherAdvisoryService` | same |
| `wadv:month_avg:v1:*` | 6 h | `WeatherAdvisoryService` | same |
| `barangays:id_name_map:v1:*` | 24 h | `Barangay::idToNameMap()` | Auto via `max(updated_at)` + row count |
| `barangay_list:v1:*` | 24 h | `BarangayApiController` | same |
| `admin_analytics:v1:*` | 5 min | `AnalyticsController` | Includes user + historical_weather versions in key |

`HistoricalWeatherCsvImporter` and `historical-weather:import` now also call `WeatherAdvisoryService::forgetHistoricalAggregateCaches()` so the next request rebuilds with the new rows.

## A8. N+1 fixes in Admin Analytics

`Admin\AnalyticsController` previously called `Barangay::nameForId()` once per row in `farmersPerBarangay` (each call hit the DB). Replaced with a single `Barangay::idToNameMap()` lookup that itself is cached for 24 hours. Same for the “top barangay” insight. Also extracted all chart/series logic into `buildAnalyticsPayload()` and wraps it in `Cache::remember()` so repeat visits return in milliseconds.

## A9. Per-request `BarangayApiController` cache + HTTP cache headers

JSON dropdown responses are cached server-side for 24 h (keyed by table version) and now respond with `Cache-Control: public, max-age=600` so the browser also short-circuits subsequent dropdown fetches.

## A10. Performance logging middleware

`App\Http\Middleware\LogSlowRequests` is appended to the global middleware stack in `bootstrap/app.php`. It:

- Counts queries via a single `DB::listen()` increment (no per-query log noise).
- Logs `perf.slow_request` at `warning` when the request crosses thresholds.
- Thresholds configurable via env: `PERF_LOG_SLOW_REQUEST_MS` (default `1500`), `PERF_LOG_QUERY_COUNT` (default `60`), `PERF_LOG_ENABLED` (default `true`).
- Records `route`, `path`, `status`, `elapsed_ms`, `query_count`, `query_time_ms`, `user_id`, `memory_mb`.

Use this to spot slow controllers, slow Python invocations, and slow OWM/Together API calls in production logs.

## A11. Scheduled cache warmer

New artisan command `agriguard:warm-caches` (in `app/Console/Commands/WarmFarmCachesCommand.php`) pre-warms:

- Historical aggregates (`monthlyRainfallTrend`, `totalRainfallByYear`, `heavyRainfallStats`).
- Barangay flood overview.
- ML weather prediction (one Python invocation, shared by every farmer).
- Per-farmer normalized weather + AI smart recommendation (capped by `--limit`).

Options: `--limit=N`, `--skip-ai`, `--skip-weather`. Every section is wrapped in try/catch so a single failure can't abort warmup.

`routes/console.php` schedules:

- `*/20 * * * *` → full warmup (`--limit=50`), `withoutOverlapping(15)`, `runInBackground()`.
- `*/5 * * * *`  → light warmup (`--limit=0 --skip-ai --skip-weather`) for shared aggregates only.

Confirmed via `php artisan schedule:list`. Production needs the standard `* * * * * php artisan schedule:run` cron entry (or a Render scheduled job) running.

## A12. Python verification

`python/predict.py` confirmed to be inference-only:

- Loads model with `joblib.load(...)` from the `.pkl` file at `python/model/xgboost_weather_model.pkl` (path overridable via env).
- Builds a 5-day forecast loop with `model.predict()` calls — never re-fits.
- All training code lives outside the request path (not in `predict.py`).

## A13. Production deployment commands

The following commands ran successfully on this workstation; production should re-run them after pull:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize

# Optional: warm the heaviest caches once before opening traffic.
php artisan agriguard:warm-caches --limit=50
```

Add the scheduler cron (any environment that should run scheduled tasks):

```cron
* * * * * cd /path/to/AgriGuard && php artisan schedule:run >> /dev/null 2>&1
```

## A14. Result snapshot

| Area | Before | After |
|------|--------|-------|
| Initial JS bundle (gzipped) | ~150+ kB monolith with eager Lucide on every page | **~17 kB** (`app` 2.6 kB + axios 15 kB), heavy chunks loaded on demand |
| Lucide on landing | Render-blocking, eager | Deferred, loaded after `load` |
| Hero image | No preload, no dimensions, no priority | Preloaded, dimensions, `fetchpriority="high"` |
| Admin analytics | N+1 barangay name lookups + uncached aggregates each visit | Single hash lookup, full payload cached 5 min |
| Weather advisory historical aggregates | Re-queried every dashboard / weather / rainfall load | Cached 6 h, busted on CSV import |
| Per-request weather lookups | One `Cache::get` per service that needed weather | Single fetch + memoized in singleton service |
| Static assets | No browser cache hints | `immutable` + 1 year for `/build/**`, gzip enabled |
| Slow requests | Hidden | Logged via `LogSlowRequests` middleware |

## A15. Open recommendations (deferred)

1. **Compress branding images** — replace `agriguard-logo.png`, `hero_image.png`, `background-image.png` with WebP/AVIF or run them through Squoosh. This is the single largest remaining LCP win on the landing page.
2. **Move to Redis** for `CACHE_STORE` and `SESSION_DRIVER` in production for multi-instance deployments and lower DB contention.
3. **Long-lived Python inference sidecar** — once OWM cache miss + Python subprocess startup is the bottleneck, fronting `python/predict.py` with a small persistent HTTP service would remove the per-call subprocess startup tax.
4. **Queue PDF/XLSX exports** in `Admin\FarmMonitoringController` / `Admin\UserManagementController` once usage justifies it — `QUEUE_CONNECTION=database` is already configured.
5. **Centralize flood-risk source of truth** — `FLOOD_RISK_BY_BARANGAY` exists in `config/barangay_flood_risk.php`, `BarangayFloodRiskOverviewService`, `StructureAnalysisService`, and `farm-map.js`. A single canonical source would prevent drift and shrink the JS payload further.

*End of round-2 addendum.*
