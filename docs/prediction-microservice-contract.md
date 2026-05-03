# Prediction Microservice Contract v1

**Service**: `insider-one-prediction-service`
**Base URL (local)**: `http://localhost:8081`
**Base URL (prod)**: see env

## Authentication

All requests must include header:

```
X-Service-Auth: <shared-secret>
```

The microservice rejects requests with missing/invalid token (401).

## Endpoints

### `GET /api/v1/health`

Health probe. Always returns 200 if service is up.

**Response**:
```json
{
  "status": "ok",
  "service": "insider-one-prediction-service",
  "version": "1.0.0"
}
```

### `POST /api/v1/predictions`

Computes championship probabilities via Monte Carlo simulation.

**Request body**:
```json
{
  "teams": [
    {
      "id": 1,
      "name": "Chelsea",
      "attack": 80,
      "defense": 75,
      "midfield": 78,
      "homeAdvantage": 1.15
    }
  ],
  "fixtures": [
    {
      "id": 1,
      "week": 1,
      "homeTeamId": 1,
      "awayTeamId": 4
    }
  ],
  "playedResults": [
    {
      "fixtureId": 1,
      "homeScore": 2,
      "awayScore": 1
    }
  ],
  "iterations": 10000,
  "seed": null
}
```

**Field rules**:
- `teams`: array, min 2, max 32. Each team: `id` int, `name` string, `attack`/`defense`/`midfield` ints 1-100, `homeAdvantage` decimal 1.00-1.30.
- `fixtures`: array. Each: `id` int, `week` int 1-50, `homeTeamId` and `awayTeamId` must exist in `teams`, must differ.
- `playedResults`: array (may be empty). Each: `fixtureId` must exist in `fixtures`, `homeScore`/`awayScore` ints 0-255.
- `iterations`: optional int 100-100000, default 10000.
- `seed`: optional int for deterministic Monte Carlo (testing only).

**Response 200**:
```json
{
  "predictions": [
    {
      "teamId": 1,
      "teamName": "Chelsea",
      "championshipProbability": 0.452,
      "expectedPoints": 13.2,
      "expectedGoalDifference": 8.7,
      "positionDistribution": {
        "1": 0.452,
        "2": 0.301,
        "3": 0.182,
        "4": 0.065
      }
    }
  ],
  "metadata": {
    "iterations": 10000,
    "algorithm": "monte_carlo_v1",
    "computedAt": "2026-05-03T12:34:56Z",
    "computeMs": 387
  }
}
```

**Probability semantics**: all probabilities are 0.0–1.0 floats (NOT percentages). Frontend formats for display.

**Response 422** (validation error):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "teams.0.attack": ["The attack must be between 1 and 100."]
  }
}
```

**Response 401** (auth failure):
```json
{ "message": "Invalid service authentication." }
```

**Response 500** (computation error):
```json
{ "message": "Prediction computation failed.", "errorId": "uuid" }
```

## Algorithm: `monte_carlo_v1`

1. Compute current standings from `playedResults`.
2. For each unplayed fixture, simulate goals via Poisson(λ) where:
   - `λ_home = LEAGUE_AVG * attack_strength(home) / defense_strength(away) * home.homeAdvantage`
   - `λ_away = LEAGUE_AVG * attack_strength(away) / defense_strength(home) / home.homeAdvantage`
   - `attack_strength(t) = (t.attack + 0.5 * t.midfield) / 100`
   - `defense_strength(t) = (t.defense + 0.5 * t.midfield) / 100`
   - `LEAGUE_AVG = 1.4` (calibrated to Premier League historical average)
3. Repeat 10,000 times. Tally finishing positions.
4. Return frequencies as probabilities.

## Constants

| Constant | Value | Rationale |
|---|---|---|
| `LEAGUE_AVG` | 1.4 | PL avg goals per team per match |
| `MIDFIELD_OFFENSIVE_WEIGHT` | 0.5 | Midfielders ~30-35% of goal contributions vs ~50-55% for forwards |
| `MIDFIELD_DEFENSIVE_WEIGHT` | 0.5 | Symmetric with offensive weight |
| `DEFAULT_ITERATIONS` | 10000 | ~±1% confidence interval, ~400ms compute |

## Calibration targets (validated by tests)

Simulator output across 10,000 random matches must satisfy:

- Avg goals per match: 2.7 ± 0.3
- Home win rate: 0.46 ± 0.05
- Draw rate: 0.25 ± 0.05
- Away win rate: 0.29 ± 0.05

## Versioning

This is contract v1. Breaking changes require `/api/v2/...` and a new `algorithm` value in metadata.
