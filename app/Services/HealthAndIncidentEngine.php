<?php
/**
 * Motor de analise topologica e calculo de indice de saude (0-100) com deteccao de causa provavel.
 */

namespace App\Services;

use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HealthAndIncidentEngine
{
    public function evaluateUnit(Unit $unit): array
    {
        $since = Carbon::now()->subMinutes(3);

        $recentMetrics = DB::table('metrics')
            ->join('series', 'metrics.series_id', '=', 'series.id')
            ->where('series.unit_id', $unit->id)
            ->where('metrics.time', '>=', $since)
            ->select('series.metric', 'metrics.value')
            ->get();

        $rtts = $recentMetrics->where('metric', 'rtt_ms')->pluck('value');
        $jitters = $recentMetrics->where('metric', 'jitter_ms')->pluck('value');
        $losses = $recentMetrics->where('metric', 'loss_pct')->pluck('value');
        $httpLatencies = $recentMetrics->where('metric', 'http_latency_ms')->pluck('value');

        $avgRtt = $rtts->isNotEmpty() ? round($rtts->avg(), 2) : 18.0;
        $avgJitter = $jitters->isNotEmpty() ? round($jitters->avg(), 2) : 2.0;
        $maxLoss = $losses->isNotEmpty() ? round($losses->max(), 2) : 0.0;
        $avgHttp = $httpLatencies->isNotEmpty() ? round($httpLatencies->avg(), 2) : $avgRtt;

        $score = 100;
        if ($maxLoss > 0) {
            $score -= min(50, $maxLoss * 3);
        }
        if ($avgRtt > 35) {
            $score -= min(30, ($avgRtt - 35) * 0.6);
        }
        if ($avgJitter > 8) {
            $score -= min(20, ($avgJitter - 8) * 1.5);
        }
        $score = max(0, min(100, (int) round($score)));

        $status = 'healthy';
        if ($score < 60) {
            $status = 'critical';
        } elseif ($score < 90) {
            $status = 'degraded';
        }

        DB::table('health_snapshots')->insert([
            'unit_id' => $unit->id,
            'score' => $score,
            'status' => $status,
            'avg_rtt' => $avgRtt,
            'avg_jitter' => $avgJitter,
            'packet_loss' => $maxLoss,
            'calculated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $incident = null;
        if ($status !== 'healthy') {
            $incident = $this->diagnoseRootCause($unit, $avgRtt, $avgJitter, $maxLoss, $avgHttp, $status);
        } else {
            DB::table('incidents')
                ->where('unit_id', $unit->id)
                ->where('status', 'open')
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return [
            'score' => $score,
            'status' => $status,
            'metrics' => [
                'rtt_ms' => $avgRtt,
                'jitter_ms' => $avgJitter,
                'loss_pct' => $maxLoss,
            ],
            'incident' => $incident,
        ];
    }

    private function diagnoseRootCause(Unit $unit, float $rtt, float $jitter, float $loss, float $http, string $status): array
    {
        $rootCause = 'local_device';
        $summary = 'Oscilação isolada na unidade de rede.';
        $severity = ($status === 'critical') ? 'critical' : 'warning';

        if ($loss > 10.0 && $rtt > 80.0) {
            $rootCause = 'uplink_carrier';
            $summary = 'Degradação severa no Enlace/Uplink WAN (alta perda associada a atraso excessivo).';
        } elseif ($rtt > 60.0 && $loss <= 2.0) {
            $rootCause = 'link_congestion';
            $summary = 'Possível saturação de banda (latência elevada mantendo baixa perda de pacotes).';
        } elseif ($http > 150.0 && $rtt < 30.0) {
            $rootCause = 'application_server';
            $summary = 'Camada de rede saudável com degradação concentrada na resposta da aplicação.';
        } elseif ($jitter > 15.0) {
            $rootCause = 'interface_flapping';
            $summary = 'Jitter crítico indicando instabilidade física de porta ou oscilação de rota.';
        }

        $existingIncident = DB::table('incidents')
            ->where('unit_id', $unit->id)
            ->where('status', 'open')
            ->where('root_cause', $rootCause)
            ->first();

        if (! $existingIncident) {
            $id = DB::table('incidents')->insertGetId([
                'unit_id' => $unit->id,
                'root_cause' => $rootCause,
                'severity' => $severity,
                'status' => 'open',
                'summary' => $summary,
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'id' => $id,
                'root_cause' => $rootCause,
                'severity' => $severity,
                'summary' => $summary,
            ];
        }

        return (array) $existingIncident;
    }
}
