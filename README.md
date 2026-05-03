# Insider One Champions League — API

Backend API for a 4-team round-robin football league simulator. Premier League rules, strength-weighted match outcomes, championship predictions for the final 3 weeks.

Companion repo: `insider-one-champions-league-web` (Vue 3 + Vite + TS).

## Stack

- PHP 8.3+
- Laravel 13.7
- PostgreSQL 17 (Docker)
- Larastan level 5 + Pint + PHPUnit
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
composer test:ci     # PHPUnit (sequential, used in CI)
composer ci          # lint:check + analyse + test:ci
```

## API contract

All routes are prefixed with `/api`. JSON keys (output and request bodies) are **camelCase**. Internal DB columns and PHP properties stay snake_case.

### Reads

| Method | Path | Returns |
|---|---|---|
| `GET` | `/teams` | `Team[]` — `{ id, name, strength }` |
| `GET` | `/fixtures` | `[ { week, fixtures: Fixture[] }, ... ]` grouped by week |
| `GET` | `/league/standings` | `{ lastPlayedWeek, nextWeek, standings: StandingRow[] }` |
| `GET` | `/predictions` | `{ lastPlayedWeek, nextWeek, predictions: TeamPrediction[] }` — `predictions: []` when `lastPlayedWeek < 3` |

### Mutations — all return the same envelope

Every mutation returns the **full simulation state** (no follow-up GETs needed):

```json
{
  "standings":      [ { "team": {...}, "played": N, "won": N, "drawn": N, "lost": N, "goalsFor": N, "goalsAgainst": N, "goalDifference": N, "points": N }, ... ],
  "predictions":    [ { "team": {...}, "championPercentage": 64.2 }, ... ],
  "fixtures":       [ { "id": N, "week": N, "homeTeam": {...}, "awayTeam": {...}, "matchResult": { "id": N, "homeScore": N, "awayScore": N } | null }, ... ],
  "lastPlayedWeek": N | null,
  "nextWeek":       N | null
}
```

| Method | Path | Status | Notes |
|---|---|---|---|
| `POST` | `/fixtures/generate` | 200 | 409 if match results exist (must `DELETE /simulation` first) |
| `POST` | `/simulation/play-week` | 200 | 409 once all weeks played |
| `POST` | `/simulation/play-all` | 200 | 409 if no unplayed fixtures remain |
| `DELETE` | `/simulation` | 200 | Wipes both `match_results` and `fixtures`, leaves `teams` |
| `PUT` | `/match-results/{id}` | 200 | Body: `{ "homeScore": N, "awayScore": N }` (camelCase, both 0–255). 422 on invalid body, 404 on unknown id |

**Empty-state guarantees:** `standings` always returns one row per team (4 zeroed rows after reset/generate); `predictions` is always an array (empty when `lastPlayedWeek < 3` or `null`); `fixtures` is `[]` after reset.

### Architecture notes

- All five mutation endpoints route through `App\Services\State\MutationStateAssembler::assemble()` — single source of truth for the response envelope. Controllers do their mutation, then return `new MutationStateResource($assembler->assemble())`.
- Predictions cost ~10,000 Monte Carlo iterations (~400ms) when `lastPlayedWeek >= 3`; gated to `[]` otherwise so most calls are cheap.

## Branching and commits

- Feature work happens on `development`.
- Each completed step is merged to `master` via PR.
- Commits follow [Conventional Commits](https://www.conventionalcommits.org/) — `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `ci`. Enforced by commitlint via Husky.
- Pint runs on staged PHP files via lint-staged on commit.
