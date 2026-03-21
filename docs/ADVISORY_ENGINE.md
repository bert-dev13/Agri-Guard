# Rule-Based Advisory Engine — AGRIGUARD

This document describes the **Rule-Based Advisory Engine** used to generate risk level and preparedness advisories for logged-in farmers.

## Overview

The engine combines:

- Real-time weather API data (forecast rainfall, rain probability, wind speed)
- Historical rainfall data (monthly averages from `historical_weather`)
- Authenticated user’s farm information (crop type, location)

It outputs a **risk level** and a **preparedness advisory** (title, message, recommended action). Logic is **rule-based only** (no AI).

## Service Class

- **`app/Services/RuleBasedAdvisoryService.php`**

### Public methods

| Method | Purpose |
|--------|--------|
| `generate(array $inputs)` | Produce advisory from explicit inputs (reusable, testable). |
| `generateForUser(User $user, array $weatherPayload)` | Build inputs from user + weather payload and return advisory (used by dashboard). |

### Input structure (for `generate()`)

```php
[
    'forecast_rainfall_mm'    => float|null,   // mm
    'rain_probability'       => int|null,     // 0–100
    'wind_speed_kmh'         => float|null,   // for future rules
    'current_month'          => int,           // 1–12
    'historical_avg_rainfall_mm' => float|null,
    'crop_type'              => string|null,
    'farm_location'          => string|null,
]
```

## Sample returned advisory structure

Every advisory returned by the engine has this shape:

```json
{
  "risk_level": "LOW",
  "advisory_title": "Conditions are manageable",
  "advisory_message": "No significant rainfall expected. Normal farm activities can proceed with usual precautions.",
  "recommended_action": "Continue monitoring the weather."
}
```

### Example by risk level

**LOW**

```json
{
  "risk_level": "LOW",
  "advisory_title": "Conditions are manageable",
  "advisory_message": "No significant rainfall expected. Normal farm activities can proceed with usual precautions.",
  "recommended_action": "Continue monitoring the weather."
}
```

**MODERATE**

```json
{
  "risk_level": "MODERATE",
  "advisory_title": "Moderate rainfall expected",
  "advisory_message": "Moderate rainfall (30–60 mm) is expected. Prepare drainage and monitor updates.",
  "recommended_action": "Prepare drainage and monitor updates. Consider delaying non-essential field work."
}
```

**HIGH**

```json
{
  "risk_level": "HIGH",
  "advisory_title": "Heavy rainfall expected",
  "advisory_message": "Heavy rainfall is expected (60 mm or more). High risk of flooding and waterlogging.",
  "recommended_action": "Secure tools, check drainage, and prepare for possible flooding. Limit field work and protect stored crops."
}
```

Additional rules can **strengthen** (e.g. LOW → MODERATE, MODERATE → HIGH) or **tailor** messages (e.g. rice → drainage/water management; vegetables/corn → extra caution).

## Rules implemented

### Base rules (rainfall only)

| Condition | Risk level |
|-----------|------------|
| Forecast rainfall > 60 mm | HIGH |
| Forecast rainfall 30–60 mm | MODERATE |
| Forecast rainfall < 30 mm | LOW |

### Additional rules

- **Rain probability > 70%** — Strengthen advisory (e.g. LOW → MODERATE, MODERATE → HIGH).
- **Historical average rainfall for current month high (≥ 50 mm)** — Strengthen advisory and mention historically wet month.
- **Crop type: rice** — Add drainage/water management focus; adjust recommended action.
- **Crop type: rain-sensitive (e.g. vegetables, corn)** — More cautionary message and actions for MODERATE/HIGH.

## Integration

- **Dashboard**: `AuthController::dashboard()` uses `WeatherAdvisoryService::getAdvisoryData($user)`, which calls `RuleBasedAdvisoryService::generateForUser()`. The main dashboard view includes `dashboard.weather-advisory`, which displays the advisory.
- **Weather details page**: `WeatherDetailsController::show()` passes `advisory` from `getAdvisoryData()` to `dashboard.weather-details`, which renders the same advisory block.

## Summary of files

| Action | File |
|--------|------|
| **Created** | `app/Services/RuleBasedAdvisoryService.php` — Rule-based advisory engine |
| **Created** | `docs/ADVISORY_ENGINE.md` — This documentation |
| **Updated** | `app/Services/WeatherAdvisoryService.php` — Injects `RuleBasedAdvisoryService`, delegates advisory to `generateForUser()` |
| **Updated** | `app/Http/Controllers/WeatherDetailsController.php` — Passes `advisory` to weather-details view |
| **Updated** | `resources/views/dashboard/weather-details.blade.php` — Advisory card (risk, title, message, recommended action) |

No admin page was added. No AI dependency; engine is fully rule-based and easy to extend.
