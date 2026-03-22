# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Development (starts Laravel server + queue + logs + Vite concurrently)
composer run dev

# Build frontend assets
npm run build

# Run tests (clears config cache first)
composer run test

# Initial setup (install deps, generate key, migrate, build frontend)
composer run setup

# Fresh database with seed data (42 cycles of real user data)
php artisan migrate:fresh --seed

# Run migrations only (no seed)
php artisan migrate

# Run a single test file
php artisan test --filter=RegistrationTest

# Clear caches after route/view changes
php artisan route:clear && php artisan view:clear
```

## Architecture

**Periodt** is a period and ovulation tracker built with Laravel 12, SQLite, Blade/Tailwind/Alpine.

### Prediction Pipeline (`app/Services/PredictionService.php`)

The core of the app. `PredictionService::predict(User)` returns a 23-field array (or null if <2 cycles with cycle_length exist) consumed directly by `dashboard.blade.php`.

The prediction runs a **4-method self-tuning ensemble**:
1. **Kalman Filter** — sequential estimator with population prior (28 days). Primary method for sparse data.
2. **Pattern Recognition** — detects alternating (long/short) and bimodal cycle distributions via k-means.
3. **CUSUM Changepoint Detection** — identifies regime shifts (stress, medication). Post-changepoint data only used for prediction.
4. **Trend Detection** — weighted linear regression on current regime.

The ensemble **backtests each method** against historical data (leave-one-out), computes MAE, and weights by inverse squared error. With <4 cycles, only the Kalman filter is used.

**Ovulation prediction** counts backwards from predicted period start using an estimated personal luteal phase (~14 days, adjusted by cycle length). If the current cycle's fertile window has passed, it automatically projects to the next cycle.

### Import Pipeline (`app/Services/ImportService.php`)

`ImportService::parse()` auto-detects format from file headers/extension and returns normalized period arrays. Supported: Clue CSV, Flo CSV, Apple Health XML, Samsung Health JSON, generic CSV. `parseManualDates()` handles the quick-entry textarea (one date per line, supports "start - end" ranges).

`ImportService::import()` skips duplicate start_dates and calls `recalculateCycleLengths()` to fill in cycle_length between consecutive periods.

### Key Patterns

- **DashboardController** is an invokable controller (`__invoke`) — route points to the class, not a method.
- **Services are injected** via controller method parameters (Laravel container auto-resolves).
- **Cycle length recalculation** runs after every store/delete/import. It sorts all user cycles by start_date and sets `cycle_length = diffInDays` to the next cycle's start. The last cycle gets `cycle_length = null`.
- **Authorization** on cycle update/delete is manual (`$cycle->user_id !== Auth::id()` → abort 403).

### Data Model

The `cycles` table is the only app-specific table: `user_id`, `start_date`, `end_date` (nullable), `cycle_length` (nullable, computed), `period_length` (nullable, computed). Indexed on `(user_id, start_date)`.

`cycle_length` is the gap between this period's start and the next period's start — it is NOT stored at log time but recalculated from the full sorted history.

### Frontend

Blade views with Tailwind CSS and Alpine.js for interactivity (tab switching on import page). Pink theme for period data, purple theme for ovulation data. No SPA — standard server-rendered pages.

### Testing

PHPUnit with in-memory SQLite (`DB_DATABASE=:memory:` in phpunit.xml). Existing tests cover auth flows and profile management. Core prediction/import services do not yet have dedicated tests.
