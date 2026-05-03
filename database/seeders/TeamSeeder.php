<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            ['name' => 'Chelsea',         'attack' => 80, 'defense' => 75, 'midfield' => 78, 'home_advantage' => 1.15],
            ['name' => 'Arsenal',         'attack' => 75, 'defense' => 70, 'midfield' => 75, 'home_advantage' => 1.15],
            ['name' => 'Manchester City', 'attack' => 78, 'defense' => 72, 'midfield' => 80, 'home_advantage' => 1.18],
            ['name' => 'Liverpool',       'attack' => 65, 'defense' => 55, 'midfield' => 60, 'home_advantage' => 1.20],
        ];

        foreach ($teams as $team) {
            Team::updateOrCreate(['name' => $team['name']], $team);
        }
    }
}
