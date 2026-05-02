<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fixture_id
 * @property int $home_score
 * @property int $away_score
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Fixture $fixture
 */
class MatchResult extends Model
{
    protected $fillable = ['fixture_id', 'home_score', 'away_score'];

    protected $casts = [
        'fixture_id' => 'integer',
        'home_score' => 'integer',
        'away_score' => 'integer',
    ];

    /** @return BelongsTo<Fixture, $this> */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }
}
