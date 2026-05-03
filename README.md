# Insider One Champions League — API

Backend for a 4-team round-robin football league simulator. Premier League rules (3 pts win, 1 draw, GD tie-break), per-team attribute model (attack / defense / midfield / home_advantage), Poisson goal model, championship predictions delegated to a dedicated microservice.

Companion repos:

- [`insider-one-champions-league-web`](https://github.com/sinanyilmaz1/insider-one-champions-league-web) — Frontend (Vue 3 + Vite + TS)
- [`insider-one-prediction-service`](https://github.com/sinanyilmaz1/insider-one-prediction-service) — Stateless Monte Carlo prediction microservice

## Architecture

```
            ┌─────────────────┐
            │  FE (Vue 3)     │  :5173
            └────────┬────────┘
                     │ HTTP
                     ▼
            ┌─────────────────┐         ┌─────────────────┐
            │  API (Laravel)  │◄───────►│  PostgreSQL     │
            │  :8000          │         │  champions_     │
            │                 │         │  league schema  │
            └────────┬────────┘         └─────────────────┘
                     │ HTTP (BFF) — POST /api/v1/predictions
                     ▼
            ┌──────────────────────┐
            │  Prediction service  │  :8081
            │  Stateless, no DB    │
            └──────────────────────┘
```

The FE only ever talks to this API. This API acts as a **BFF** (Backend-for-Frontend), forwarding league state to the prediction service and proxying the response back. The microservice is never exposed to the browser.

## Stack

- PHP 8.3+ (works on 8.4 / 8.5)
- Laravel 13.7
- PostgreSQL 17 (Docker)
- Larastan level 5 + Pint + PHPUnit
- Husky + commitlint + lint-staged (conventional commits, Pint on staged files)

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

# 4. Migrate + seed
php artisan migrate:fresh --seed

# 5. Serve
php artisan serve --port=8000
```

The init script at `docker/init/01-init.sh` runs on first volume init and creates the `insider_one_test` database plus the `champions_league` schema in both databases.

## Required environment variables

| Variable | Purpose |
|---|---|
| `APP_KEY` | Laravel encryption key |
| `DB_*` | PostgreSQL connection (defaults match `docker-compose.yml`) |
| `PREDICTION_SERVICE_URL` | Base URL of the microservice (e.g. `http://127.0.0.1:8081`) |
| `PREDICTION_SERVICE_AUTH_TOKEN` | **Must match** the microservice's `SERVICE_AUTH_TOKEN` |
| `PREDICTION_SERVICE_TIMEOUT` | HTTP timeout in seconds (default 5) |
| `CORS_ALLOWED_ORIGINS` | Comma-separated origins allowed to call from a browser. **Required in non-local env**; defaults to `http://localhost:5173` only when `APP_ENV=local` |
| `RATE_LIMIT_READ` | Read-endpoint requests per minute per IP (default 60) |
| `RATE_LIMIT_MUTATE` | Mutation-endpoint requests per minute per IP (default 20) |

## Database layout

- **Database:** `insider_one` (dev), `insider_one_test` (testing)
- **Schema:** `champions_league` — all application tables live here, not in `public`. The `pgsql` connection has `search_path = champions_league`.

The `teams` table holds four orthogonal per-team attributes:

| Column | Type | Range | Constraint |
|---|---|---|---|
| `attack` | `unsignedSmallInt` | 1–100 | CHECK |
| `defense` | `unsignedSmallInt` | 1–100 | CHECK |
| `midfield` | `unsignedSmallInt` | 1–100 | CHECK |
| `home_advantage` | `decimal(4,2)` | 1.00–1.30 | CHECK |

These replace the original `strength` (1-10) field. The new attributes feed both:

- The per-match Poisson `MatchSimulator` (used by `play-week` / `play-all` to write actual match results)
- The microservice's championship predictor (Monte Carlo over 10k iterations)

Both simulators carry a `SYNCHRONIZATION NOTE` comment and are validated by identical PL-tolerance calibration tests in both repos.

## API contract

All routes are prefixed with `/api`. JSON keys (output and request bodies) are **camelCase**. Internal DB columns and PHP properties stay snake_case.

### Reads — `throttle:api-read` (60/min default)

| Method | Path | Returns |
|---|---|---|
| `GET` | `/teams` | `Team[]` — `{ id, name, attack, defense, midfield, homeAdvantage }` |
| `GET` | `/fixtures` | `[ { week, fixtures: Fixture[] }, ... ]` grouped by week |
| `GET` | `/league/standings` | `{ lastPlayedWeek, nextWeek, standings: StandingRow[] }` |
| `GET` | `/predictions` | `{ lastPlayedWeek, nextWeek, predictions: TeamPrediction[] }` — `predictions: []` when `lastPlayedWeek < 3` or microservice fails |

### Mutations — `throttle:api-mutate` (20/min default), all return the same envelope

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
| `POST` | `/fixtures/generate` | 200 | 409 if results exist (must `DELETE /simulation` first) |
| `POST` | `/simulation/play-week` | 200 | 409 once all weeks played |
| `POST` | `/simulation/play-all` | 200 | 409 if no unplayed fixtures remain |
| `DELETE` | `/simulation` | 200 | Wipes both `match_results` and `fixtures`, leaves `teams` |
| `PUT` | `/match-results/{id}` | 200 | Body: `{ "homeScore": N, "awayScore": N }` (camelCase, both 0-255). 422 invalid, 404 unknown |

**Empty-state guarantees:** `standings` always returns one row per team (4 zeroed rows after reset/generate); `predictions` is always an array (empty when `lastPlayedWeek < 3` or when the microservice is unreachable); `fixtures` is `[]` after reset.

### BFF behavior — graceful degradation

When `lastPlayedWeek >= 3`, the assembler builds a contract payload and calls the prediction service. If the service is unreachable, returns 5xx, returns 429 (rate-limited), or returns 401/422 (auth/contract issue) — the API:

1. Logs the failure with context
2. Returns `predictions: []` instead of bubbling the error to the FE
3. The rest of the envelope (standings, fixtures, lastPlayedWeek, nextWeek) renders normally

The FE never sees an error toast for prediction failures — the panel just shows "no predictions yet."

## Security

- **Per-IP rate limiting** on all `/api/*` routes via Laravel's throttle middleware. Two buckets: `api-read` (60/min) and `api-mutate` (20/min). Tunable via env.
- **CORS** locked to `CORS_ALLOWED_ORIGINS`. Defaults to empty when `APP_ENV != local` so a missing env var fails closed (browser requests blocked) instead of silently falling through to a localhost default.
- **Service-to-service auth** with a shared secret in the `X-Service-Auth` header on every microservice request.

## Common commands

```bash
composer lint        # apply Pint formatting
composer lint:check  # check formatting only
composer analyse     # PHPStan / Larastan level 5
composer test        # PHPUnit
composer test:ci     # PHPUnit (used in CI)
composer ci          # lint:check + analyse + test:ci
```

## Tests

51 tests covering:

- **Unit:** standings calculator, fixture generator (round-robin invariants), prediction service HTTP client (all error branches)
- **Feature:** simulation envelope shape, BFF happy path + graceful degrade (5xx, 429, connection failure), rate limit (429 + bucket isolation), CORS (allowed/disallowed origin behavior), match-result update validation, simulator calibration against PL averages

```bash
composer test
```

## Branching and commits

- Feature work happens on `development`.
- Each completed step is merged to `master` via PR.
- Commits follow [Conventional Commits](https://www.conventionalcommits.org/) — `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `ci`. Enforced by commitlint via Husky.
- Pint runs on staged PHP files via lint-staged on commit.

## Microservice contract

The HTTP contract between this API and the prediction service is documented at [`docs/prediction-microservice-contract.md`](docs/prediction-microservice-contract.md). Breaking changes require `/api/v2/...` and a new `algorithm` value in the response metadata.
