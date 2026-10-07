<?php
/**
 * Simulador de laboratorio: gera telemetria por dispositivo (ping) e por enlace (SNMP) da unidade e
 * envia em lote para a API de ingestao. A cada ciclo le as falhas ativas de fault_injections e aplica
 * seus efeitos conforme a hierarquia da topologia. Dispositivos inalcançaveis reportam 100% de perda e
 * deixam de enviar RTT e jitter, e enlaces cujo equipamento de origem esta inalcançavel deixam de ser medidos.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class LabSimulate extends Command
{
    protected $signature = 'mg:lab-simulate {unit=unidade-teste} {--interval=5} {--cycles=0}';

    protected $description = 'Simula telemetria por dispositivo e enlace e envia para a API de ingestao';

    public function handle(): int
    {
        $unit = DB::table('units')->where('slug', $this->argument('unit'))->first();

        if (! $unit) {
            $this->error('Unidade não encontrada.');

            return self::FAILURE;
        }

        $token = config('multiguard.lab_token');

        if (! $token) {
            $this->error('Defina MG_LAB_TOKEN no .env e rode php artisan config:clear.');

            return self::FAILURE;
        }

        $devices = DB::table('devices')->where('unit_id', $unit->id)->orderBy('id')->get()->keyBy('id');
        $links = DB::table('links')->where('unit_id', $unit->id)->orderBy('id')->get()->keyBy('id');

        if ($devices->isEmpty()) {
            $this->error('Unidade sem dispositivos. Rode o LabTopologySeeder.');

            return self::FAILURE;
        }

        $parentLink = $links->keyBy('to_device_id');
        $paths = [];
        $ancestors = [];

        foreach ($devices as $device) {
            $paths[$device->id] = [];
            $ancestors[$device->id] = [$device->id];
            $current = $device->id;

            while (isset($parentLink[$current])) {
                $link = $parentLink[$current];
                $paths[$device->id][] = $link->id;
                $current = $link->from_device_id;
                $ancestors[$device->id][] = $current;
            }
        }

        $interval = max(1, (int) $this->option('interval'));
        $maxCycles = (int) $this->option('cycles');
        $cycle = 0;

        $this->info("Simulando {$devices->count()} dispositivos e {$links->count()} enlaces. Ctrl+C para parar.");

        while (true) {
            $cycle++;
            $time = now()->toIso8601String();

            $faults = DB::table('fault_injections')
                ->where('unit_id', $unit->id)
                ->where('started_at', '<=', $time)
                ->where('ended_at', '>', $time)
                ->get();

            $points = $this->buildPoints($devices, $links, $paths, $ancestors, $faults, $time);

            try {
                $status = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(5)
                    ->post(config('multiguard.ingest_url'), ['points' => $points])
                    ->status();
            } catch (\Throwable $e) {
                $status = 'sem conexão';
            }

            $this->line(sprintf(
                '[%s] ciclo %d | %d pontos | falhas ativas: %d | HTTP %s',
                now($unit->timezone)->format('H:i:s'),
                $cycle,
                count($points),
                $faults->count(),
                $status
            ));

            if ($maxCycles > 0 && $cycle >= $maxCycles) {
                break;
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }

    private function buildPoints($devices, $links, array $paths, array $ancestors, $faults, string $time): array
    {
        $deviceState = [];

        foreach ($devices as $device) {
            $depth = count($paths[$device->id]);
            $deviceState[$device->id] = [
                'reachable' => true,
                'loss' => mt_rand(0, 99) < 3 ? 0.5 : 0.0,
                'rtt' => 1.0 + $depth * 0.8 + $this->rnd(0, 0.6),
                'jitter' => 0.2 + $this->rnd(0, 0.4),
            ];
        }

        $linkState = [];

        foreach ($links as $link) {
            $linkState[$link->id] = [
                'status' => 1,
                'util' => $this->rnd(8, 35),
                'errors' => 0.0,
            ];
        }

        foreach ($faults as $fault) {
            if ($fault->kind === 'device_down') {
                foreach ($devices as $device) {
                    if (in_array($fault->device_id, $ancestors[$device->id])) {
                        $deviceState[$device->id]['reachable'] = false;
                    }
                }

                continue;
            }

            $affected = $devices->filter(fn ($device) => in_array($fault->link_id, $paths[$device->id]));

            if ($fault->kind === 'link_down') {
                $linkState[$fault->link_id]['status'] = 0;
                $linkState[$fault->link_id]['util'] = 0.0;

                foreach ($affected as $device) {
                    $deviceState[$device->id]['reachable'] = false;
                }
            }

            if ($fault->kind === 'port_errors') {
                $linkState[$fault->link_id]['errors'] = $this->rnd(40, 90);

                foreach ($affected as $device) {
                    $deviceState[$device->id]['loss'] = max($deviceState[$device->id]['loss'], $this->rnd(4, 9));
                    $deviceState[$device->id]['rtt'] += $this->rnd(2, 6);
                }
            }

            if ($fault->kind === 'congestion') {
                $linkState[$fault->link_id]['util'] = $this->rnd(92, 99);

                foreach ($affected as $device) {
                    $deviceState[$device->id]['rtt'] += $this->rnd(35, 70);
                    $deviceState[$device->id]['jitter'] += $this->rnd(3, 8);
                    $deviceState[$device->id]['loss'] += $this->rnd(0, 1);
                }
            }
        }

        $points = [];

        foreach ($devices as $device) {
            $state = $deviceState[$device->id];
            $loss = $state['reachable'] ? $state['loss'] : 100.0;

            $points[] = $this->point('ping_loss_pct', 'agent', 'icmp', $loss, $time, $device->id, null);

            if ($state['reachable']) {
                $points[] = $this->point('ping_rtt_ms', 'agent', 'icmp', $state['rtt'], $time, $device->id, null);
                $points[] = $this->point('ping_jitter_ms', 'agent', 'icmp', $state['jitter'], $time, $device->id, null);
            }
        }

        foreach ($links as $link) {
            if (! $deviceState[$link->from_device_id]['reachable']) {
                continue;
            }

            $state = $linkState[$link->id];

            $points[] = $this->point('if_oper_status', 'snmp', 'port', $state['status'], $time, null, $link->id);
            $points[] = $this->point('if_util_pct', 'snmp', 'port', $state['util'], $time, null, $link->id);
            $points[] = $this->point('if_errors_per_min', 'snmp', 'port', $state['errors'], $time, null, $link->id);
        }

        return $points;
    }

    private function point(string $metric, string $source, string $key, float $value, string $time, ?int $deviceId, ?int $linkId): array
    {
        $point = [
            'metric' => $metric,
            'source' => $source,
            'key' => $key,
            'time' => $time,
            'value' => round($value, 2),
        ];

        if ($deviceId !== null) {
            $point['device_id'] = $deviceId;
        }

        if ($linkId !== null) {
            $point['link_id'] = $linkId;
        }

        return $point;
    }

    private function rnd(float $min, float $max): float
    {
        return $min + ($max - $min) * mt_rand() / mt_getrandmax();
    }
}