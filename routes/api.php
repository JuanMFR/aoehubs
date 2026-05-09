<?php

use App\Http\Controllers\Api\CompanionApiController;
use Illuminate\Support\Facades\Route;

// Rate limiting:
//   - 'companion-poll'  = endpoints alta frecuencia (poll cada 5s, presence
//                         ping cada 30s). Generoso: 60/min.
//   - 'companion-write' = mutaciones del estado del match. 30/min cubre
//                         operativa normal y previene flooding.
//   - 'companion-replay'= upload de replay (archivos grandes). 5/min por
//                         user — un user no deberia subir mas que eso.
Route::middleware(['auth:sanctum', 'throttle:companion-poll'])->prefix('companion')->group(function () {
    Route::get('match',          [CompanionApiController::class, 'pendingMatch']);
    Route::post('heartbeat',     [CompanionApiController::class, 'heartbeat']);
});

Route::middleware(['auth:sanctum', 'throttle:companion-write'])->prefix('companion')->group(function () {
    Route::post('pings',         [CompanionApiController::class, 'reportPings']);
    Route::post('lobby-ready',   [CompanionApiController::class, 'reportLobbyReady']);
    Route::post('match-started', [CompanionApiController::class, 'matchStarted']);
    Route::post('match-aborted', [CompanionApiController::class, 'matchAborted']);
});

Route::middleware(['auth:sanctum', 'throttle:companion-replay'])->prefix('companion')->group(function () {
    Route::post('replay',        [CompanionApiController::class, 'uploadReplay']);
});
