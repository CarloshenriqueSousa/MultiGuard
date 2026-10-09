<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/dashboard');
});
Route::get('/dashboard', function () {
    return view('dashboard');
});
Route::get('/map', fn () => Inertia::render('Map', [
    'unitSlug' => 'unidade-teste',
]));
