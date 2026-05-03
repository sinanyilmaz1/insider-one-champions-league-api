<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fixtures;

use App\Models\Team;
use App\Services\Fixtures\FixtureGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class FixtureGeneratorTest extends TestCase
{
    private function team(int $id, string $name): Team
    {
        $team = new Team;
        $team->id = $id;
        $team->name = $name;

        return $team;
    }

    /**
     * @return list<Team>
     */
    private function teams(int $count): array
    {
        $teams = [];
        for ($i = 1; $i <= $count; $i++) {
            $teams[] = $this->team($i, "Team$i");
        }

        return $teams;
    }

    private function generator(): FixtureGenerator
    {
        return new FixtureGenerator(new Randomizer(new Mt19937(42)));
    }

    #[Test]
    public function rejects_odd_number_of_teams(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generator()->generate($this->teams(3));
    }

    #[Test]
    public function rejects_fewer_than_two_teams(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generator()->generate([]);
    }

    #[Test]
    public function produces_double_round_robin_count_for_four_teams(): void
    {
        // 4 teams: each plays 3 others home + away = 6 matches per team / 2 = 12 fixtures
        $fixtures = $this->generator()->generate($this->teams(4), seed: 42);
        self::assertCount(12, $fixtures);
    }

    #[Test]
    public function produces_double_round_robin_count_for_six_teams(): void
    {
        // 6 teams: 6*5 = 30 fixtures
        $fixtures = $this->generator()->generate($this->teams(6), seed: 42);
        self::assertCount(30, $fixtures);
    }

    #[Test]
    public function week_count_is_double_team_count_minus_one(): void
    {
        $fixtures = $this->generator()->generate($this->teams(4), seed: 42);
        $weeks = array_unique(array_map(fn ($f) => $f->week, $fixtures));
        sort($weeks);

        self::assertSame([1, 2, 3, 4, 5, 6], $weeks);
    }

    #[Test]
    public function each_week_has_half_the_team_count_in_matches(): void
    {
        $fixtures = $this->generator()->generate($this->teams(4), seed: 42);
        $byWeek = [];
        foreach ($fixtures as $f) {
            $byWeek[$f->week] = ($byWeek[$f->week] ?? 0) + 1;
        }

        foreach ($byWeek as $week => $count) {
            self::assertSame(2, $count, "Week $week should have 2 matches");
        }
    }

    #[Test]
    public function no_team_plays_itself(): void
    {
        $fixtures = $this->generator()->generate($this->teams(4), seed: 42);
        foreach ($fixtures as $f) {
            self::assertNotSame($f->home->id, $f->away->id);
        }
    }

    #[Test]
    public function every_team_pair_meets_exactly_twice_once_per_venue(): void
    {
        $fixtures = $this->generator()->generate($this->teams(4), seed: 42);

        $homeAway = [];
        foreach ($fixtures as $f) {
            $key = "{$f->home->id}-{$f->away->id}";
            $homeAway[$key] = ($homeAway[$key] ?? 0) + 1;
        }

        // Each ordered pair (home,away) appears exactly once
        $teams = $this->teams(4);
        foreach ($teams as $h) {
            foreach ($teams as $a) {
                if ($h->id === $a->id) {
                    continue;
                }
                self::assertSame(1, $homeAway["{$h->id}-{$a->id}"] ?? 0, "Pair {$h->id}-{$a->id} should appear once");
            }
        }
    }

    #[Test]
    public function seed_produces_deterministic_output(): void
    {
        $teams = $this->teams(4);
        $a = $this->generator()->generate($teams, seed: 99);
        $b = $this->generator()->generate($teams, seed: 99);

        $serialize = fn (array $fixtures) => array_map(
            fn ($f) => "{$f->week}:{$f->home->id}v{$f->away->id}",
            $fixtures,
        );

        self::assertSame($serialize($a), $serialize($b));
    }
}
