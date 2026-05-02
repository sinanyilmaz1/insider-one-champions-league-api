<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $week
 * @property int $home_team_id
 * @property int $away_team_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Team $homeTeam
 * @property-read Team $awayTeam
 * @property-read MatchResult|null $matchResult
 */
class Fixture extends Model
{
    protected $fillable = ['week', 'home_team_id', 'away_team_id'];

    protected $casts = [
        'week' => 'integer',
        'home_team_id' => 'integer',
        'away_team_id' => 'integer',
    ];

    /** @return BelongsTo<Team, $this> */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /** @return HasOne<MatchResult, $this> */
    public function matchResult(): HasOne
    {
        return $this->hasOne(MatchResult::class);
    }
}
