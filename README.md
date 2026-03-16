# Periodt.

Track your cycle. Know your body.

Periodt is a period and ovulation tracker with an AI-powered prediction engine that gets smarter the more you use it. Built with Laravel.

## Features

- **Smart Period Predictions** — 4-method self-tuning ensemble (Kalman filter, pattern recognition, changepoint detection, trend regression) that backtests against your history and automatically weights the most accurate method for your body
- **Ovulation & Fertile Window** — estimates your personal luteal phase length and projects ovulation date, fertile window (6 days), and peak fertility (3 days) with confidence ratings
- **Cycle Insights** — detects alternating patterns, bimodal distributions, regime shifts (stress, medication changes), and gradual lengthening/shortening trends
- **Import From Other Apps** — auto-detects and parses exports from Clue, Flo, Apple Health, and Samsung Health. Also supports generic CSV and manual quick entry
- **Confidence Scoring** — real statistical confidence intervals that tighten as more data is logged, with regularity classification (very regular → irregular)

## Tech Stack

- **Backend:** Laravel 12, PHP 8.5, SQLite
- **Frontend:** Blade, Tailwind CSS, Alpine.js
- **Auth:** Laravel Breeze
- **Build:** Vite

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm

### Setup

```bash
git clone https://github.com/Colissa/periodt.git
cd periodt
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
```

### Run

```bash
# Start everything (server + queue + logs + Vite dev)
composer run dev

# Or just the server
php artisan serve
```

Visit [http://localhost:8000](http://localhost:8000), create an account, and start logging periods. Predictions kick in after 2 logged periods.

## How the Prediction Engine Works

Most period apps use a simple average. Periodt runs four independent prediction methods and combines them using an ensemble that evaluates which methods have been most accurate for your specific cycle history:

| Method | What It Does |
|--------|-------------|
| **Kalman Filter** | Optimal sequential estimator (same math as GPS). Balances trusting new data vs. filtering noise. |
| **Pattern Recognition** | Detects alternating long/short cycles and bimodal distributions that averages destroy. |
| **Changepoint Detection** | CUSUM algorithm identifies when your cycle fundamentally shifts and discards old-regime data. |
| **Trend Regression** | Weighted linear regression catches gradual lengthening or shortening. |

The ensemble backtests each method against your history, measures which ones have been most accurate, and weights them accordingly. The best-performing method gets the most influence.

**Ovulation** is predicted by estimating your personal luteal phase length (the biologically stable ~14-day phase before your period) and counting backwards from the predicted period start — not the naive "day 14" rule.

## Importing Data

Go to the **Import** tab after logging in. Supported sources:

- **Clue** — Settings → Data Export → Download CSV
- **Flo** — Profile → Settings → Export Data
- **Apple Health** — Profile → Export All Health Data → upload `export.xml`
- **Samsung Health** — use Quick Entry (Samsung doesn't export period data in their standard export)
- **Generic CSV** — any CSV with a `start_date` column
- **Quick Entry** — type dates manually, one per line, in any format

## Testing

```bash
composer run test
```

## License

MIT
