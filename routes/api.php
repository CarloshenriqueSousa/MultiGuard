<?php
/**
 * Rotas da API. A ingestao (POST /api/ingest) autentica por token de unidade e e a unica aberta a agentes. Todas
 * as demais sao de leitura para o console e exigem usuario interno (middleware console).
 */

use App\Http\Controllers\HealthController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IngestController;
use App\Http\Controllers\LatestController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\TopologyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['ingest', 'throttle:120,1'])->post('/ingest', IngestController::class);

Route::middleware('console')->group(function () {
    Route::get('/units/{unit}/topology', [TopologyController::class, 'show']);
    Route::get('/units/{unit}/health', [HealthController::class, 'show']);
    Route::get('/units/{unit}/latest', LatestController::class);
    Route::get('/units/{unit}/series', SeriesController::class);
    Route::get('/units/{unit}/incidents', [IncidentController::class, 'index']);
});
