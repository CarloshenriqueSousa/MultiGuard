<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\IngestController;
use App\Http\Controllers\TopologyController;
use App\Http\Controllers\MetricQueryController;
use App\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|

*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware(['ingest', 'throttle:120,1'])
    ->post('/ingest', IngestController::class);
Route::get('/units/{unit}/topology', [TopologyController::class, 'show']);
Route::get('/metrics/query', [MetricQueryController::class, 'query']);
Route::get('/units/{unit}/health', [HealthController::class, 'show']);
