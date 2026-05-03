<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedSmallInteger('attack')->default(50)->after('name');
            $table->unsignedSmallInteger('defense')->default(50)->after('attack');
            $table->unsignedSmallInteger('midfield')->default(50)->after('defense');
            $table->decimal('home_advantage', 4, 2)->default(1.10)->after('midfield');
        });

        DB::statement('
            UPDATE champions_league.teams
            SET attack = strength * 10,
                defense = strength * 10,
                midfield = strength * 10,
                home_advantage = 1.15
        ');

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('strength');
        });

        DB::statement('ALTER TABLE champions_league.teams ADD CONSTRAINT teams_attack_range CHECK (attack BETWEEN 1 AND 100)');
        DB::statement('ALTER TABLE champions_league.teams ADD CONSTRAINT teams_defense_range CHECK (defense BETWEEN 1 AND 100)');
        DB::statement('ALTER TABLE champions_league.teams ADD CONSTRAINT teams_midfield_range CHECK (midfield BETWEEN 1 AND 100)');
        DB::statement('ALTER TABLE champions_league.teams ADD CONSTRAINT teams_home_adv_range CHECK (home_advantage BETWEEN 1.00 AND 1.30)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE champions_league.teams DROP CONSTRAINT IF EXISTS teams_attack_range');
        DB::statement('ALTER TABLE champions_league.teams DROP CONSTRAINT IF EXISTS teams_defense_range');
        DB::statement('ALTER TABLE champions_league.teams DROP CONSTRAINT IF EXISTS teams_midfield_range');
        DB::statement('ALTER TABLE champions_league.teams DROP CONSTRAINT IF EXISTS teams_home_adv_range');

        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedTinyInteger('strength')->default(5);
        });

        DB::statement('UPDATE champions_league.teams SET strength = LEAST(10, GREATEST(1, attack / 10))');

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['attack', 'defense', 'midfield', 'home_advantage']);
        });
    }
};
