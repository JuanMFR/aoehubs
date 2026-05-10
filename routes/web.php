<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CivDraftController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MapDraftController;
use App\Http\Controllers\MapVoteController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Steam OpenID login — rate-limited para prevenir abuso del callback
Route::middleware('throttle:auth-web')->group(function () {
    Route::get('/login', [AuthController::class, 'redirectToSteam'])->name('login');
    Route::get('/auth/steam/callback', [AuthController::class, 'handleSteamCallback'])->name('auth.steam.callback');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Pagina publica del companion — info, requisitos, link de descarga.
Route::view('/companion', 'companion')->name('companion');

// Leaderboard + perfiles públicos. Throttle ligero para prevenir scraping.
Route::middleware('throttle:public-read')->group(function () {
    Route::get('/leaderboard',          [LeaderboardController::class, 'index'])->name('leaderboard');
    Route::get('/users/{steamId}',      [UserProfileController::class, 'show'])->name('users.show')
        ->where('steamId', '\d{17}'); // SteamID64 son siempre 17 digitos
});

// Rutas que requieren login
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/companion/token', [AuthController::class, 'generateCompanionToken'])
        ->middleware('throttle:5,1')
        ->name('companion.token');

    Route::get('/matches',                [MatchController::class, 'index'])->name('matches.index');
    Route::post('/matches',               [MatchController::class, 'store'])->name('matches.store');
    Route::post('/matches/test-host',     [MatchController::class, 'testHost'])->name('matches.test-host');
    Route::post('/matches/test-joiner',   [MatchController::class, 'testJoiner'])->name('matches.test-joiner');
    Route::get('/matches/{id}',           [MatchController::class, 'show'])->name('matches.show')->where('id', '\d+');
    Route::post('/matches/{id}/cancel',   [MatchController::class, 'cancel'])->name('matches.cancel');

    Route::post('/queue/join',  [QueueController::class, 'join'])
        ->middleware('throttle:10,1')
        ->name('queue.join');
    Route::post('/queue/leave', [QueueController::class, 'leave'])->name('queue.leave');
    Route::get('/queue/status', [QueueController::class, 'status'])->name('queue.status');

    // Voto de pool: solo POST. El modal se renderiza inline en el dashboard
    // (ver _map_vote_modal partial), no hay vista separada.
    Route::post('/maps/vote', [MapVoteController::class, 'submit'])
        ->middleware('throttle:30,1')
        ->name('maps.vote.submit');

    Route::get('/matches/{id}/draft/maps',        [MapDraftController::class, 'show'])->name('drafts.maps.show');
    Route::get('/matches/{id}/draft/maps/state',  [MapDraftController::class, 'state'])->name('drafts.maps.state');
    Route::post('/matches/{id}/draft/maps/ban',   [MapDraftController::class, 'ban'])->name('drafts.maps.ban');

    Route::get('/matches/{id}/draft/civs',        [CivDraftController::class, 'show'])->name('drafts.civs.show');
    Route::get('/matches/{id}/draft/civs/state',  [CivDraftController::class, 'state'])->name('drafts.civs.state');
    Route::post('/matches/{id}/draft/civs/picks', [CivDraftController::class, 'submitPicks'])->name('drafts.civs.picks');
    Route::post('/matches/{id}/draft/civs/bans',  [CivDraftController::class, 'submitBans'])->name('drafts.civs.bans');
    Route::post('/matches/{id}/draft/civs/final', [CivDraftController::class, 'submitFinal'])->name('drafts.civs.final');
});

// Admin (requiere middleware 'admin' que verifica role === admin)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                     [AdminController::class, 'overview'])->name('overview');
    Route::get('/users',                [AdminController::class, 'users'])->name('users');
    Route::post('/users/{user}/role',   [AdminController::class, 'promoteUser'])->name('users.promote');
    Route::get('/matches',              [AdminController::class, 'matches'])->name('matches');
    Route::get('/matches/{match}',      [AdminController::class, 'matchDetail'])->name('matches.show');
    Route::post('/matches/{match}/cancel',    [AdminController::class, 'forceCancel'])->name('matches.cancel');
    Route::post('/matches/{match}/reprocess', [AdminController::class, 'reprocess'])->name('matches.reprocess');

    Route::get('/seasons',                       [AdminController::class, 'seasons'])->name('seasons');
    Route::post('/seasons/{season}/ends-at',     [AdminController::class, 'updateSeasonEndsAt'])->name('seasons.ends-at');
    Route::post('/seasons/{season}/close',       [AdminController::class, 'closeSeason'])->name('seasons.close');

    Route::get('/maps',                  [AdminController::class, 'maps'])->name('maps');
    Route::post('/maps',                 [AdminController::class, 'storeMap'])->name('maps.store');
    Route::post('/maps/extract-replay',  [AdminController::class, 'extractMapFromReplay'])->name('maps.extract-replay');
    Route::patch('/maps/{map}',          [AdminController::class, 'updateMap'])->name('maps.update');
    Route::post('/maps/{map}/toggle',    [AdminController::class, 'toggleMap'])->name('maps.toggle');
    Route::delete('/maps/{map}',         [AdminController::class, 'destroyMap'])->name('maps.destroy');

    // Categorias de mapas (ladders por tipo)
    Route::get('/map-categories',                  [AdminController::class, 'mapCategories'])->name('map-categories');
    Route::post('/map-categories',                 [AdminController::class, 'storeMapCategory'])->name('map-categories.store');
    Route::patch('/map-categories/{category}',     [AdminController::class, 'updateMapCategory'])->name('map-categories.update');
    Route::post('/map-categories/{category}/toggle', [AdminController::class, 'toggleMapCategory'])->name('map-categories.toggle');
    Route::delete('/map-categories/{category}',    [AdminController::class, 'destroyMapCategory'])->name('map-categories.destroy');

    // Map pool voting
    Route::get('/map-votes',                 [AdminController::class, 'mapVotes'])->name('map-votes');
    Route::post('/map-votes',                [AdminController::class, 'storeMapVote'])->name('map-votes.store');
    Route::get('/map-votes/{vote}',          [AdminController::class, 'showMapVote'])->name('map-votes.show');
    Route::post('/map-votes/{vote}/cancel',  [AdminController::class, 'cancelMapVote'])->name('map-votes.cancel');
    Route::post('/map-votes/{vote}/apply',   [AdminController::class, 'applyMapVote'])->name('map-votes.apply');
});
