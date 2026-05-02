<?php

namespace App\Services\Fixtures;

use App\Models\Team;

final readonly class FixturePair
{
    public function __construct(
        public int $week,
        public Team $home,
        public Team $away,
    ) {}
}
