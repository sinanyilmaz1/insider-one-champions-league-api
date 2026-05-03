<?php

declare(strict_types=1);

namespace Tests\Feature\Simulation;

use App\Models\Team;
use App\Services\Simulation\MatchSimulator;
use PHPUnit\Framework\Attributes\Test;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class MatchSimulatorCalibrationTest extends TestCase
{
    /**
     * Asserts the simulator produces output consistent with PL historical averages.
     * If this fails, the model has drifted from reality — investigate constants.
     */
    #[Test]
    public function simulator_matches_premier_league_averages(): void
    {
        $simulator = new MatchSimulator(new Randomizer);
        $iterations = 10_000;

        $totalGoals = 0;
        $homeWins = 0;
        $draws = 0;
        $awayWins = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $home = $this->randomTeam();
            $away = $this->randomTeam();
            $outcome = $simulator->simulate($home, $away);

            $totalGoals += $outcome->homeScore + $outcome->awayScore;

            if ($outcome->homeScore > $outcome->awayScore) {
                $homeWins++;
            } elseif ($outcome->homeScore < $outcome->awayScore) {
                $awayWins++;
            } else {
                $draws++;
            }
        }

        $avgGoals = $totalGoals / $iterations;
        $homeWinRate = $homeWins / $iterations;
        $drawRate = $draws / $iterations;
        $awayWinRate = $awayWins / $iterations;

        self::assertEqualsWithDelta(2.7, $avgGoals, 0.3, "Avg goals/match should be ~2.7 (got {$avgGoals})");
        self::assertEqualsWithDelta(0.46, $homeWinRate, 0.05, "Home win rate should be ~46% (got {$homeWinRate})");
        self::assertEqualsWithDelta(0.25, $drawRate, 0.05, "Draw rate should be ~25% (got {$drawRate})");
        self::assertEqualsWithDelta(0.29, $awayWinRate, 0.05, "Away win rate should be ~29% (got {$awayWinRate})");
    }

    #[Test]
    public function seeded_simulator_is_deterministic(): void
    {
        $home = $this->fixedTeam('Chelsea', 80, 75, 78, 1.15);
        $away = $this->fixedTeam('Liverpool', 65, 55, 60, 1.20);

        $sim1 = new MatchSimulator(new Randomizer(new Mt19937(42)));
        $sim2 = new MatchSimulator(new Randomizer(new Mt19937(42)));

        $r1 = $sim1->simulate($home, $away);
        $r2 = $sim2->simulate($home, $away);

        self::assertSame($r1->homeScore, $r2->homeScore);
        self::assertSame($r1->awayScore, $r2->awayScore);
    }

    private function randomTeam(): Team
    {
        $team = new Team;
        $team->id = random_int(1, 9999);
        $team->name = 'Random'.$team->id;
        $team->attack = random_int(40, 90);
        $team->defense = random_int(40, 90);
        $team->midfield = random_int(40, 90);
        $team->home_advantage = random_int(105, 125) / 100.0;

        return $team;
    }

    private function fixedTeam(string $name, int $atk, int $def, int $mid, float $homeAdv): Team
    {
        $team = new Team;
        $team->id = (int) crc32($name);
        $team->name = $name;
        $team->attack = $atk;
        $team->defense = $def;
        $team->midfield = $mid;
        $team->home_advantage = $homeAdv;

        return $team;
    }
}
