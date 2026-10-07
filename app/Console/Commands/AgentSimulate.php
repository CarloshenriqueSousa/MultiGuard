<?php

namespace App\Console\Commands;

use App\Models\IngestToken;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AgentSimulate extends Command
{
    protected $signature = 'mg:agent-simulate
                            {unit : slug da unidade}
                            {--token= : token de ingestão (se omitido, busca o último ativo da unidade)}
                            {--interval=5 : intervalo entre coletas em segundos}
                            {--spikes : injeta instabilidades e picos aleatórios para teste}';

    protected $description = 'Simula a coleta contínua de um agente e envia lotes de telemetria para a API de ingestão';

    public function handle(): int
    {
        $unitSlug = $this->argument('unit');
        $unit = Unit::where('slug', $unitSlug)->first();

        if (! $unit) {
            $this->error("Unidade '{$unitSlug}' não encontrada.");
            return self::FAILURE;
        }

        // Obtém o token (via parâmetro ou do banco se estiver em laboratório)
        $token = $this->option('token');
        if (! $token) {
            $this->warn("Token não fornecido via --token. Certifique-se de passar um token gerado.");
            $token = $this->ask("Cole o token de ingestão (mg_...):");
        }

        $interval = (int) $this->option('interval');
        $withSpikes = $this->option('spikes');
        $url = 'http://127.0.0.1:8000/api/ingest';

        $this->info("Iniciando simulador para a unidade: [{$unit->name}]");
        $this->line("Alvo da API: {$url}");
        $this->line("Intervalo: {$interval}s | Modo instabilidade: " . ($withSpikes ? 'ATIVO' : 'DESATIVADO'));
        $this->comment("Pressione CTRL+C para parar a simulação.\n");

        $baseRtt = 18.0;

        while (true) {
            $now = now()->toIso8601String();

            $jitter = round(abs(sin(microtime(true))) * 4 + (mt_rand(0, 20) / 10), 2);
            $rtt = round($baseRtt + (mt_rand(-30, 40) / 10) + ($jitter * 0.5), 2);
            $loss = 0.0;

            if ($withSpikes && mt_rand(1, 8) === 1) {
                $rtt += mt_rand(50, 180);
                $jitter += mt_rand(15, 45);
                $loss = mt_rand(0, 1) === 1 ? (float) mt_rand(5, 25) : 0.0;
                $this->warn("[ANOMALIA SIMULADA] Pico de latência/perda gerado neste ciclo!");
            }

            $points = [
                [
                    'metric' => 'rtt_ms',
                    'source' => 'agent',
                    'key' => 'gateway',
                    'time' => $now,
                    'value' => max(1.0, $rtt),
                ],
                [
                    'metric' => 'jitter_ms',
                    'source' => 'agent',
                    'key' => 'gateway',
                    'time' => $now,
                    'value' => max(0.1, $jitter),
                ],
                [
                    'metric' => 'loss_pct',
                    'source' => 'agent',
                    'key' => 'gateway',
                    'time' => $now,
                    'value' => $loss,
                ],
                [
                    'metric' => 'http_latency_ms',
                    'source' => 'agent',
                    'key' => 'server_health',
                    'time' => $now,
                    'value' => round($rtt + mt_rand(8, 20), 2),
                ],
            ];

            $response = Http::withToken($token)
                ->acceptJson()
                ->post($url, ['points' => $points]);

            if ($response->successful()) {
                $data = $response->json();
                $timeFormatted = now()->format('H:i:s');
                $this->line("[{$timeFormatted}] Lote enviado: " . count($points) . " pontos | Gravados: {$data['stored']} | RTT: {$rtt}ms | Jitter: {$jitter}ms | Loss: {$loss}%");
            } else {
                $this->error("Erro no envio [Status {$response->status()}]: " . $response->body());
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
