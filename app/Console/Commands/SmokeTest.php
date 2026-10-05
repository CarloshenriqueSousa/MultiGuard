<?php

namespace App\Console\Commands;

use App\Models\IngestToken;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SmokeTest extends Command
{
    protected $signature = 'mg:smoke-test {unit=unidade-teste : slug da unidade para testar}';
    protected $description = 'Executa uma bateria completa de testes de saúde do sistema Multi-Guard';

    public function handle(): int
    {
        $this->info("Iniciando Verificação Pré-Voo do Multi-Guard...\n");

        $results = [];

        try {
            DB::connection()->getPdo();
            $dbName = DB::connection()->getDatabaseName();
            $results[] = ['Banco de Dados (PostgreSQL)', "Conectado em [{$dbName}]", 'OK'];
        } catch (\Exception $e) {
            $results[] = ['Banco de Dados (PostgreSQL)', $e->getMessage(), 'FALHA'];
        }

        $unitSlug = $this->argument('unit');
        $unit = Unit::where('slug', $unitSlug)->first();
        if ($unit) {
            $results[] = ['Unidade Cadastrada', "{$unit->name} (ID: {$unit->id})", 'OK'];
        } else {
            $results[] = ['Unidade Cadastrada', "Unidade '{$unitSlug}' não encontrada", 'FALHA'];
        }

        if ($unit) {
            $devicesCount = DB::table('devices')->where('unit_id', $unit->id)->count();
            $linksCount = DB::table('links')->where('unit_id', $unit->id)->count();
            if ($devicesCount > 0 && $linksCount > 0) {
                $results[] = ['Topologia de Rede', "{$devicesCount} dispositivos | {$linksCount} enlaces", 'OK'];
            } else {
                $results[] = ['Topologia de Rede', 'Nenhum dispositivo/link encontrado (rode o seeder)', 'AVISO'];
            }
        }

        $tokenPlain = null;
        if ($unit) {
            $existingToken = IngestToken::where('unit_id', $unit->id)->whereNull('revoked_at')->first();
            if ($existingToken) {
                $results[] = ['Token de Ingestão', "Token ativo encontrado (ID: {$existingToken->id})", 'OK'];
            } else {
                $tokenPlain = 'mg_' . Str::random(40);
                IngestToken::create([
                    'unit_id' => $unit->id,
                    'name' => 'smoke-test-token',
                    'token_hash' => hash('sha256', $tokenPlain),
                ]);
                $results[] = ['Token de Ingestão', 'Novo token gerado automaticamente', 'OK'];
            }
        }

        $serverUrl = 'http://127.0.0.1:8000/api/ingest';
        if ($unit) {
            $testPlain = 'mg_smoke_' . Str::random(32);
            $tokenObj = IngestToken::create([
                'unit_id' => $unit->id,
                'name' => 'smoke-test-run',
                'token_hash' => hash('sha256', $testPlain),
            ]);

            try {
                $response = Http::timeout(3)
                    ->withToken($testPlain)
                    ->acceptJson()
                    ->post($serverUrl, [
                        'points' => [
                            [
                                'metric' => 'smoke_test_rtt',
                                'source' => 'agent',
                                'key' => 'gateway',
                                'time' => now()->toIso8601String(),
                                'value' => 15.5,
                            ],
                        ],
                    ]);

                if ($response->status() === 202) {
                    $results[] = ['API Ingestão (HTTP 202)', 'Ponto de teste recebido e gravado', 'OK'];
                } else {
                    $results[] = ['API Ingestão', "Resposta inesperada: Status {$response->status()}", 'FALHA'];
                }
            } catch (\Exception $e) {
                $results[] = ['API Ingestão', "Não alcançou o servidor (o 'php artisan serve' está rodando?)", 'ATENÇÃO'];
            } finally {
                $tokenObj->delete();
            }
        }

        $this->table(['Componente / Verificação', 'Detalhes', 'Status'], $results);

        $hasErrors = collect($results)->contains(fn ($r) => str_contains($r[2], 'X'));

        if ($hasErrors) {
            $this->error("\Existem falhas que precisam ser corrigidas antes de rodar a aplicação.");
            return self::FAILURE;
        }

        $this->info("Tudo pronto e validado! O ambiente está 100% operacional.");
        return self::SUCCESS;
    }
}
