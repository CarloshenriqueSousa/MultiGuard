<?php
/**
 * Rotas web do console interno: dashboard e mapa 3D, paginas React servidas pelo Inertia. Todas atras do
 * middleware console (usuario interno por HTTP Basic).
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('console')->group(function () {
    Route::get('/', fn () => redirect('/dashboard'));

    Route::get('/dashboard', fn () => Inertia::render('Dashboard', ['unitSlug' => 'unidade-teste']));

    Route::get('/map', fn () => Inertia::render('Map', ['unitSlug' => 'unidade-teste']));
});
