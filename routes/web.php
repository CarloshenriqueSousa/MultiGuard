<?php
/**
 * Rotas web do console interno: login, logout, paginas Inertia e APIs de leitura do console.
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\LatestController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\TopologyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('console')->group(function () {
    Route::get('/', fn () => redirect('/dashboard'));
    Route::get('/dashboard', fn () => Inertia::render('Dashboard', ['unitSlug' => 'unidade-teste']));
    Route::get('/map', fn () => Inertia::render('Map', ['unitSlug' => 'unidade-teste']));

    Route::prefix('api')->group(function () {
        Route::get('/units/{unit}/topology', [TopologyController::class, 'show']);
        Route::get('/units/{unit}/health', [HealthController::class, 'show']);
        Route::get('/units/{unit}/latest', LatestController::class);
        Route::get('/units/{unit}/series', SeriesController::class);
        Route::get('/units/{unit}/incidents', [IncidentController::class, 'index']);
    });
});
