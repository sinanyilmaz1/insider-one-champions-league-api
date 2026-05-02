<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('week');
            $table->foreignId('home_team_id')->constrained('champions_league.teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('champions_league.teams')->cascadeOnDelete();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE champions_league.fixtures ADD CONSTRAINT fixtures_different_teams CHECK (home_team_id <> away_team_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
