<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $attack
 * @property int $defense
 * @property int $midfield
 * @property float $home_advantage
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Team extends Model
{
    protected $fillable = ['name', 'attack', 'defense', 'midfield', 'home_advantage'];

    protected $casts = [
        'attack' => 'integer',
        'defense' => 'integer',
        'midfield' => 'integer',
        'home_advantage' => 'decimal:2',
    ];

    /** @return HasMany<Fixture, $this> */
    public function homeFixtures(): HasMany
    {
        return $this->hasMany(Fixture::class, 'home_team_id');
    }

    /** @return HasMany<Fixture, $this> */
    public function awayFixtures(): HasMany
    {
        return $this->hasMany(Fixture::class, 'away_team_id');
    }
}
