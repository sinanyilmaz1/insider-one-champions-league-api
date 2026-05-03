<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Standings;

use App\Models\Team;
use App\Services\Standings\LeagueStandingsCalculator;
use App\Services\Standings\ScoredMatch;
use App\Services\Standings\StandingRow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LeagueStandingsCalculatorTest extends TestCase
{
    private function team(int $id, string $name): Team
    {
        $team = new Team;
        $team->id = $id;
        $team->name = $name;

        return $team;
    }

    /** @return list<Team> */
    private function fourTeams(): array
    {
        return [
            $this->team(1, 'Chelsea'),
            $this->team(2, 'Arsenal'),
            $this->team(3, 'Manchester City'),
            $this->team(4, 'Liverpool'),
        ];
    }

    private function calculator(): LeagueStandingsCalculator
    {
        return new LeagueStandingsCalculator;
    }

    #[Test]
    public function empty_matches_yields_zeroed_rows_for_every_team(): void
    {
        $rows = $this->calculator()->calculate($this->fourTeams(), []);

        self::assertCount(4, $rows);
        foreach ($rows as $row) {
            self::assertSame(0, $row->played);
            self::assertSame(0, $row->won);
            self::assertSame(0, $row->drawn);
            self::assertSame(0, $row->lost);
            self::assertSame(0, $row->goalsFor);
            self::assertSame(0, $row->goalsAgainst);
            self::assertSame(0, $row->goalDifference);
            self::assertSame(0, $row->points);
        }
    }

    #[Test]
    public function awards_three_points_for_a_win_and_zero_for_a_loss(): void
    {
        $rows = $this->calculator()->calculate($this->fourTeams(), [
            new ScoredMatch(homeTeamId: 1, awayTeamId: 2, homeScore: 2, awayScore: 1),
        ]);

        $chelsea = $this->find($rows, 1);
        $arsenal = $this->find($rows, 2);

        self::assertSame(3, $chelsea->points);
        self::assertSame(1, $chelsea->won);
        self::assertSame(0, $chelsea->lost);
        self::assertSame(0, $arsenal->points);
        self::assertSame(0, $arsenal->won);
        self::assertSame(1, $arsenal->lost);
    }

    #[Test]
    public function awards_one_point_each_for_a_draw(): void
    {
        $rows = $this->calculator()->calculate($this->fourTeams(), [
            new ScoredMatch(homeTeamId: 1, awayTeamId: 2, homeScore: 2, awayScore: 2),
        ]);

        self::assertSame(1, $this->find($rows, 1)->points);
        self::assertSame(1, $this->find($rows, 1)->drawn);
        self::assertSame(1, $this->find($rows, 2)->points);
        self::assertSame(1, $this->find($rows, 2)->drawn);
    }

    #[Test]
    public function accumulates_goals_for_and_against_correctly(): void
    {
        $rows = $this->calculator()->calculate($this->fourTeams(), [
            new ScoredMatch(homeTeamId: 1, awayTeamId: 2, homeScore: 3, awayScore: 1),
            new ScoredMatch(homeTeamId: 3, awayTeamId: 1, homeScore: 0, awayScore: 2),
        ]);

        $chelsea = $this->find($rows, 1);
        self::assertSame(2, $chelsea->played);
        self::assertSame(5, $chelsea->goalsFor);
        self::assertSame(1, $chelsea->goalsAgainst);
        self::assertSame(4, $chelsea->goalDifference);
    }

    #[Test]
    public function sorts_by_points_then_goal_difference_then_goals_for_then_name(): void
    {
        // Three teams tied at 3 points. Tie-break: GD, then GF, then name asc.
        $rows = $this->calculator()->calculate($this->fourTeams(), [
            new ScoredMatch(homeTeamId: 1, awayTeamId: 4, homeScore: 5, awayScore: 0), // Chelsea +5 GD
            new ScoredMatch(homeTeamId: 2, awayTeamId: 4, homeScore: 3, awayScore: 0), // Arsenal +3 GD
            new ScoredMatch(homeTeamId: 3, awayTeamId: 4, homeScore: 3, awayScore: 0), // ManC    +3 GD
        ]);

        // Chelsea first (best GD)
        self::assertSame('Chelsea', $rows[0]->team->name);
        // Arsenal vs ManC: same points (3), same GD (+3), same GF (3) → name asc → Arsenal before ManC
        self::assertSame('Arsenal', $rows[1]->team->name);
        self::assertSame('Manchester City', $rows[2]->team->name);
        // Liverpool last (0 points, -11 GD)
        self::assertSame('Liverpool', $rows[3]->team->name);
    }

    /**
     * @param  list<StandingRow>  $rows
     */
    private function find(array $rows, int $teamId): StandingRow
    {
        foreach ($rows as $row) {
            if ($row->team->id === $teamId) {
                return $row;
            }
        }
        self::fail("Team id $teamId not found");
    }
}
