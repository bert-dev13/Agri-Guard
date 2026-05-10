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

*End of report.*
