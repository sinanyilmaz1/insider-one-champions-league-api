<?php

namespace App\Services\Fixtures;

use App\Models\Team;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

class FixtureGenerator
{
    public function __construct(private readonly Randomizer $randomizer) {}

    /**
     * Generate a double round-robin schedule using the circle method.
     *
     * @param  list<Team>  $teams
     * @return list<FixturePair>
     */
    public function generate(array $teams, ?int $seed = null): array
    {
        $count = count($teams);

        if ($count < 2 || $count % 2 !== 0) {
            throw new InvalidArgumentException('Number of teams must be an even number greater than 1.');
        }

        $randomizer = $seed === null
            ? $this->randomizer
            : new Randomizer(new Mt19937($seed));

        /** @var list<Team> $rotation */
        $rotation = $randomizer->shuffleArray($teams);

        $weeksPerLeg = $count - 1;
        $matchesPerWeek = intdiv($count, 2);

        $firstLeg = [];
        for ($week = 1; $week <= $weeksPerLeg; $week++) {
            for ($i = 0; $i < $matchesPerWeek; $i++) {
                $home = $rotation[$i];
                $away = $rotation[$count - 1 - $i];
                $firstLeg[] = new FixturePair($week, $home, $away);
            }

            $rotation = $this->rotate($rotation);
        }

        $secondLeg = [];
        foreach ($firstLeg as $pair) {
            $secondLeg[] = new FixturePair(
                $pair->week + $weeksPerLeg,
                $pair->away,
                $pair->home,
            );
        }

        return [...$firstLeg, ...$secondLeg];
    }

    /**
     * @param  list<Team>  $teams
     * @return list<Team>
     */
    private function rotate(array $teams): array
    {
        $first = $teams[0];
        $last = array_pop($teams);
        $rest = array_slice($teams, 1);

        return [$first, $last, ...$rest];
    }
}
