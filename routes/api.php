<?php

use App\Http\Controllers\FixtureController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MatchResultController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/teams', [TeamController::class, 'index']);

Route::get('/fixtures', [FixtureController::class, 'index']);
Route::post('/fixtures/generate', [FixtureController::class, 'generate']);

Route::post('/simulation/play-week', [SimulationController::class, 'playWeek']);
Route::post('/simulation/play-all', [SimulationController::class, 'playAll']);
Route::delete('/simulation', [SimulationController::class, 'reset']);

Route::get('/league/standings', [LeagueController::class, 'standings']);
Route::get('/predictions', [PredictionController::class, 'index']);

Route::put('/match-results/{matchResult}', [MatchResultController::class, 'update']);
