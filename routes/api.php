<?php

use App\Http\Controllers\Api\CompanionApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('companion')->group(function () {
    Route::get('match',          [CompanionApiController::class, 'pendingMatch']);
    Route::post('pings',         [CompanionApiController::class, 'reportPings']);
    Route::post('lobby-ready',   [CompanionApiController::class, 'reportLobbyReady']);
    Route::post('match-started', [CompanionApiController::class, 'matchStarted']);
    Route::post('match-aborted', [CompanionApiController::class, 'matchAborted']);
    Route::post('heartbeat',     [CompanionApiController::class, 'heartbeat']);
    Route::post('replay',        [CompanionApiController::class, 'uploadReplay']);
});
