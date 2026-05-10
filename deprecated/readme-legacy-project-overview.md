# AgriGuard - Current System Overview

AgriGuard is a Laravel-based farm support web application focused on weather-aware decision support for farmers.
The current build provides secure user access, dashboard summaries, weather monitoring, rainfall trend analysis, and farm/account settings management.

## 1) System Purpose (Current)

- Provide farmers with quick daily weather visibility.
- Show rainfall trend history for better planning and preparedness.
- Keep farm profile data tied to user-specific recommendations and display context.
- Offer a clean, mobile-friendly dashboard UI with consistent card-based design.
- Deliver live Together AI recommendations per page context (Dashboard, Weather, Rainfall) with safe fallback behavior.

## 2) Core Features Available Right Now

### Authentication and Access
- Login and registration flow.
- Email verification via OTP-style verification endpoints.
- Protected authenticated routes with verified-email middleware.
- Logout support.

### Dashboard
- Personalized greeting and farm snapshot.
- Current weather summary (temperature, rain chance/rainfall, humidity, wind).
- Compact 5-day forecast panel.
- Live AI smart recommendation card (Together AI) with:
  - AI API status and model (debug view)
  - Main action, score/confidence, risk
  - Time-of-day plan, avoid list, water strategy, and "Why this recommendation?" (expandable)
- Quick action links to Weather and Farm/Settings areas.

### Weather Details
- Current weather featured section.
- Header action button: **View Rainfall Trends** (Rainfall is a Weather sub-feature).
- 5-day forecast list.
- Hourly forecast cards.
- Additional weather details cards (feel, rain chance, moisture indicators, cloud level).
- Weather trends panel for quick visual comparison.
- Dedicated Weather-page AI recommendation flow (separate controller logic, not shared service):
  - Uses Together AI with weather-focused payload and prompt
  - Normalized JSON output fields
  - Weather-specific fallback recommendation when AI fails/malformed
  - AI status/model display and expandable "Why this recommendation?" section

### Historical Rainfall Trends
- Page redesigned to match Dashboard + Weather visual language.
- Header with location/crop context, data period chips, and **Back to Weather** + breadcrumb (`Weather / Rainfall`).
- Four summary cards:
  - Today's Rainfall
  - This Week's Rainfall
  - This Month's Rainfall
  - Rain Status (Light/Moderate/Heavy)
- Rainfall-specific AI recommendation card (Together AI), distinct from Dashboard/Weather:
  - Main Rainfall Advice
  - Rainfall Risk Score
  - AI Confidence
  - Rainfall Insight
  - Field Action Plan (Early Day, Midday, Late Day)
  - Drainage/Irrigation Advice
  - What to Avoid Today
  - Rainfall Risk Level
- Rainfall-aware fallback advisory if AI output fails/empty/invalid JSON.
- Main chart card with filter tabs:
  - Daily
  - Weekly
  - Monthly
  - Yearly
- Rainfall history list in scan-friendly rows.

### Settings
- Account update (profile details).
- Password update.
- Farm profile update.

### Public and Support Pages
- Public landing page for guests.
- Forgot password placeholder page (future implementation hook).

## 3) Route Map (Web)

Defined in `routes/web.php`:

- Public:
  - `/` (landing, auto-redirect to dashboard if authenticated)
  - `/login`, `/register`
  - `/verify-email`, `/resend-verification-code`
  - `/forgot-password`
  - `/api/amulung-barangays`
- Auth + verified:
  - `/dashboard`
  - `/weather`
  - `/weather/rainfall`
  - `/settings`
  - Update routes for account/password/farm
  - `/logout`
- Legacy redirects:
  - `/weather-details` -> `/weather`
  - `/rainfall-trends` -> `/weather/rainfall`
  - `/farm-profile` -> `/settings`

## 4) Current Main Backend Controllers

Located in `app/Http/Controllers`:

- `AuthController.php` - auth, registration, verification, dashboard entry.
- `WeatherController.php` - weather API endpoints.
- `WeatherDetailsController.php` - weather details page logic.
- `RainfallTrendsController.php` - rainfall trend analytics and view data prep.
- `SettingsController.php` - account/password/farm settings updates.
- `PsgcController.php` - PSGC/barangay API proxy support.

## 5) Frontend Structure (Current)

### Blade Views
- Layout:
  - `resources/views/layouts/user.blade.php`
- User pages:
  - `resources/views/user/dashboard.blade.php`
  - `resources/views/user/weather/weather-details.blade.php`
  - `resources/views/user/rainfall/rainfall-trends.blade.php`
  - `resources/views/user/settings/settings.blade.php`

### Page Scripts
- `resources/js/user/dashboard.js`
- `resources/js/user/weather.js`
- `resources/js/user/rainfall-trends.js`
- `resources/js/user/settings.js`
- `resources/js/user/user-navbar.js`

### Page Styles
- `resources/css/user/dashboard.css`
- `resources/css/user/weather.css`
- `resources/css/user/rainfall-trends.css`
- `resources/css/user/settings.css`
- `resources/css/user/user-navbar.css`

### Global Asset Entry
- `resources/js/app.js`
- `resources/css/app.css`

### Navigation Structure (Current)
- Top navigation now shows only:
  - Dashboard
  - Weather
- Rainfall is no longer a top-level menu item.
- Rainfall remains accessible through:
  - Weather page header button (`View Rainfall Trends`)
  - Direct route `/weather/rainfall`

## 6) UI/UX Design State

Current user module follows a shared design language:

- Soft neutral page backgrounds.
- Rounded white cards with subtle shadows.
- Green accent palette for farm/weather context.
- Clear typographic hierarchy.
- Lucide icon usage across cards and headers.
- Responsive layouts tuned for mobile and tablet-first usage.

## 7) Notes and Next Potential Improvements

- Forgot password flow is currently a placeholder and can be fully wired.
- More location-aware rainfall labels can be added for local climate thresholds.
- Add feature tests around AI fallback paths (empty/malformed Together AI responses).
- Consider optional caching/rate protection for AI calls to reduce latency and API cost.

