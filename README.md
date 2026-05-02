# Insider One Champions League — API

Backend API for a 4-team round-robin football league simulator. Premier League rules, strength-weighted match outcomes, championship predictions for the final 3 weeks.

Companion repo: `insider-one-champions-league-web` (Vue 3 + Vite + TS).

## Stack

- PHP 8.3+
- Laravel 13.7
- PostgreSQL 17 (Docker)
- Larastan level 5 + Pint + PHPUnit/ParaTest
- Husky + commitlint (conventional commits)

## Prerequisites

- PHP 8.3 or newer with the `pdo_pgsql` extension
- Composer
- Docker (for Postgres)
- Node.js (for Husky hooks only)

## Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment files
cp .env.example .env
cp .env.testing.example .env.testing
php artisan key:generate
php artisan key:generate --env=testing

# 3. Start Postgres
docker compose up -d
```

On a **fresh Docker volume**, the init script at `docker/init/01-init.sh` runs automatically and creates:

- The `insider_one_test` database (peer of `insider_one`)
- The `champions_league` schema in both databases

If you are working with an **existing volume** (the init script only fires on first init), create the schema manually:

```bash
docker compose exec postgres psql -U insider_one -d insider_one      -c 'CREATE SCHEMA IF NOT EXISTS champions_league;'
docker compose exec postgres psql -U insider_one -d insider_one_test -c 'CREATE SCHEMA IF NOT EXISTS champions_league;'
```

Verify:

```bash
docker compose exec postgres psql -U insider_one -d insider_one -c '\dn'
```

You should see both `public` and `champions_league`.

## Database layout

- **Database:** `insider_one` (dev), `insider_one_test` (testing)
- **Schema:** `champions_league` — all application tables live here, not in `public`. The Laravel `pgsql` connection is configured with `search_path = champions_league` in `config/database.php`.

## Common commands

```bash
composer lint        # apply Pint formatting
composer lint:check  # check formatting only
composer analyse     # PHPStan / Larastan level 5
composer test        # PHPUnit
composer test:ci     # PHPUnit in parallel
composer ci          # lint:check + analyse + test:ci
```

## Branching and commits

- Feature work happens on `development`.
- Each completed step is merged to `master` via PR.
- Commits follow [Conventional Commits](https://www.conventionalcommits.org/) — `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `ci`. Enforced by commitlint via Husky.
- Pint runs on staged PHP files via lint-staged on commit.
