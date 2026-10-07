<?php
/**
 * Configuracoes do Multi-Guard lidas do .env: token de ingestao usado pelo simulador de laboratorio
 * e URL da API. Ficam em config para funcionar mesmo com o cache de configuracao ativo.
 */

return [
    'lab_token' => env('MG_LAB_TOKEN'),
    'ingest_url' => env('MG_INGEST_URL', 'http://127.0.0.1:8000/api/ingest'),
];
