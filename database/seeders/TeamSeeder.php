<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            ['name' => 'Chelsea', 'strength' => 8],
            ['name' => 'Arsenal', 'strength' => 7],
            ['name' => 'Manchester City', 'strength' => 7],
            ['name' => 'Liverpool', 'strength' => 5],
        ];

        foreach ($teams as $team) {
            Team::updateOrCreate(['name' => $team['name']], $team);
        }
    }
}
