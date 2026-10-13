<?php
/**
 * Rotas da API externa. Apenas a ingestao de telemetria vinda dos agentes, autenticada por token de unidade.
 * As rotas de leitura do console ficam em web.php para compartilhar a sessao do navegador.
 */

use App\Http\Controllers\IngestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['ingest', 'throttle:120,1'])->post('/ingest', IngestController::class);
