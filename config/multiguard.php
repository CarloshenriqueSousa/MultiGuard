<?php
/**
 * Configuracoes do Multi-Guard: token e URL de ingestao do simulador de laboratorio e parametros do motor
 * de diagnostico. A janela de avaliacao deve ter pelo menos 3 vezes o intervalo de coleta (3 amostras para a
 * mediana). A retencao de snapshots e aplicada pelo comando mg:prune. Os limiares sao fixos nesta versao; a
 * baseline por enlace do documento entra depois.
 */

return [
    'lab_token' => env('MG_LAB_TOKEN'),
    'ingest_url' => env('MG_INGEST_URL', 'http://127.0.0.1:8000/api/ingest'),
    'eval_window_seconds' => (int) env('MG_EVAL_WINDOW', 20),
    'resolve_after_seconds' => (int) env('MG_RESOLVE_AFTER', 30),
    'snapshot_retention_days' => (int) env('MG_SNAPSHOT_RETENTION_DAYS', 7),
    'thresholds' => [
        'rtt_warn' => 15.0,
        'jitter_warn' => 3.0,
        'errors_warn' => 5.0,
        'util_warn' => 90.0,
    ],
];
