<?php
/**
 * Motor de diagnostico do Multi-Guard. A cada avaliacao le a janela recente de telemetria da unidade,
 * classifica cada dispositivo (ping) e cada enlace (SNMP) usando a mediana da janela, calcula o indice de
 * saude 0-100 e aplica as regras de causa provavel sobre a arvore de topologia: o enlace com problema e o que
 * esta atras dele (raio de impacto) explicam os dispositivos afetados, e so os dispositivos sem explicacao
 * viram falha local. Dispositivo sem dados na janela e informacao (no_data), nunca saudavel. Incidentes sao
 * agrupados por causa e dispositivo raiz, encerrados apos alguns segundos sem evidencia, e a primeira deteccao
 * e gravada em fault_injections para medir deteccao (K1) e acerto da causa (K3).
 */

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DiagnosisEngine
{
    public function evaluate(object $unit): array
    {
        $now = now();
        $since = $now->copy()->subSeconds((int) config('multiguard.eval_window_seconds'));

        $devices = DB::table('devices')->where('unit_id', $unit->id)->orderBy('id')->get()->keyBy('id');
        $links = DB::table('links')->where('unit_id', $unit->id)->orderBy('id')->get()->keyBy('id');

        $readings = $this->readings($unit->id, $since);
        $deviceStates = $this->classifyDevices($devices, $readings);
        $linkStates = $this->classifyLinks($links, $readings);
        $findings = $this->diagnose($devices, $links, $deviceStates, $linkStates);

        $scores = collect($deviceStates)->pluck('score')->filter(fn ($score) => $score !== null);
        $unitScore = $scores->isEmpty() ? null : (int) round($scores->avg());
        $unitStatus = $unitScore === null ? 'no_data' : $this->statusFromScore($unitScore);

        $this->snapshot($unit, $devices, $links, $deviceStates, $linkStates, $unitScore, $unitStatus, $now);
        $this->persist($unit, $findings, $now);

        return [
            'score' => $unitScore,
            'status' => $unitStatus,
            'findings' => array_map(
                fn ($finding) => $finding['cause'].'@'.($finding['root_device_id'] ? $devices[$finding['root_device_id']]->name : '-'),
                $findings
            ),
        ];
    }

    private function readings(int $unitId, Carbon $since): array
    {
        $rows = DB::select(
            'SELECT s.device_id, s.link_id, s.metric,
                    percentile_disc(0.5) WITHIN GROUP (ORDER BY m.value) AS median
             FROM metrics m
             JOIN series s ON s.id = m.series_id
             WHERE s.unit_id = ? AND m.time >= ?
             GROUP BY s.device_id, s.link_id, s.metric',
            [$unitId, $since->toIso8601String()]
        );

        $result = ['device' => [], 'link' => []];

        foreach ($rows as $row) {
            if ($row->device_id !== null) {
                $result['device'][$row->device_id][$row->metric] = (float) $row->median;
            } elseif ($row->link_id !== null) {
                $result['link'][$row->link_id][$row->metric] = (float) $row->median;
            }
        }

        return $result;
    }

    private function classifyDevices(Collection $devices, array $readings): array
    {
        $config = config('multiguard.thresholds');
        $states = [];

        foreach ($devices as $device) {
            $data = $readings['device'][$device->id] ?? [];

            if (! isset($data['ping_loss_pct'])) {
                $states[$device->id] = ['status' => 'no_data', 'score' => null, 'rtt' => null];

                continue;
            }

            if ($data['ping_loss_pct'] >= 99.0) {
                $states[$device->id] = ['status' => 'down', 'score' => 0, 'rtt' => null];

                continue;
            }

            $rtt = $data['ping_rtt_ms'] ?? 0.0;
            $jitter = $data['ping_jitter_ms'] ?? 0.0;

            $score = 100.0
                - min(50.0, $data['ping_loss_pct'] * 5)
                - min(30.0, max(0.0, $rtt - $config['rtt_warn']))
                - min(20.0, max(0.0, $jitter - $config['jitter_warn']) * 3);

            $score = (int) round(max(0.0, $score));

            $states[$device->id] = ['status' => $this->statusFromScore($score), 'score' => $score, 'rtt' => $rtt];
        }

        return $states;
    }

    private function classifyLinks(Collection $links, array $readings): array
    {
        $states = [];

        foreach ($links as $link) {
            $data = $readings['link'][$link->id] ?? [];

            if (! isset($data['if_oper_status'])) {
                $states[$link->id] = ['status' => 'no_data', 'score' => null, 'up' => null, 'errors' => 0.0, 'util' => 0.0];

                continue;
            }

            $up = $data['if_oper_status'] >= 1.0;
            $errors = $data['if_errors_per_min'] ?? 0.0;
            $util = $data['if_util_pct'] ?? 0.0;

            $score = $up ? (int) round(100 - min(50.0, $errors) - min(40.0, max(0.0, $util - 80) * 2)) : 0;

            $states[$link->id] = [
                'status' => $up ? $this->statusFromScore($score) : 'down',
                'score' => $score,
                'up' => $up,
                'errors' => $errors,
                'util' => $util,
            ];
        }

        return $states;
    }

    private function diagnose(Collection $devices, Collection $links, array $deviceStates, array $linkStates): array
    {
        $config = config('multiguard.thresholds');
        $bad = ['down', 'critical', 'degraded'];
        $children = [];
        $parent = [];

        foreach ($links as $link) {
            $children[$link->from_device_id][] = $link->to_device_id;
            $parent[$link->to_device_id] = $link->from_device_id;
        }

        $findings = [];
        $covered = [];

        foreach ($links as $link) {
            $state = $linkStates[$link->id];

            if ($state['status'] === 'no_data') {
                continue;
            }

            $affected = $this->subtree($link->to_device_id, $children);
            $multiple = count($affected) > 1;
            $cause = null;

            if (! $state['up']) {
                $cause = $multiple ? 'uplink' : 'local_device';
            } elseif ($state['errors'] >= $config['errors_warn']) {
                $cause = $multiple ? 'uplink' : 'cable_port';
            } elseif ($state['util'] >= $config['util_warn'] && $this->hasHighRtt($affected, $deviceStates, $config['rtt_warn'])) {
                $cause = 'congestion';
            }

            if ($cause === null) {
                continue;
            }

            $findings[] = [
                'cause' => $cause,
                'root_device_id' => $link->to_device_id,
                'link_id' => $link->id,
                'severity' => $this->severityFor($affected, $deviceStates, ! $state['up']),
                'affected' => $affected,
                'evidence' => ['up' => $state['up'], 'errors' => $state['errors'], 'util' => $state['util']],
            ];

            foreach ($affected as $id) {
                $covered[$id] = true;
            }
        }

        foreach ($devices as $device) {
            $state = $deviceStates[$device->id];

            if (isset($covered[$device->id]) || ! in_array($state['status'], $bad, true)) {
                continue;
            }

            $parentId = $parent[$device->id] ?? null;

            if ($parentId !== null && in_array($deviceStates[$parentId]['status'], $bad, true)) {
                continue;
            }

            $affected = array_values(array_filter(
                $this->subtree($device->id, $children),
                fn ($id) => ! isset($covered[$id]) && in_array($deviceStates[$id]['status'], $bad, true)
            ));

            $findings[] = [
                'cause' => 'local_device',
                'root_device_id' => $device->id,
                'link_id' => null,
                'severity' => in_array($state['status'], ['down', 'critical'], true) ? 'critical' : 'warning',
                'affected' => $affected,
                'evidence' => ['status' => $state['status'], 'score' => $state['score']],
            ];
        }

        $silent = array_keys(array_filter($deviceStates, fn ($state) => $state['status'] === 'no_data'));

        if ($silent !== []) {
            $findings[] = [
                'cause' => 'no_data',
                'root_device_id' => null,
                'link_id' => null,
                'severity' => count($silent) === $devices->count() ? 'critical' : 'warning',
                'affected' => $silent,
                'evidence' => ['silent_devices' => count($silent), 'total_devices' => $devices->count()],
            ];
        }

        return $findings;
    }

    private function subtree(int $root, array $children): array
    {
        $found = [$root];

        foreach ($children[$root] ?? [] as $child) {
            $found = array_merge($found, $this->subtree($child, $children));
        }

        return $found;
    }

    private function hasHighRtt(array $ids, array $deviceStates, float $limit): bool
    {
        foreach ($ids as $id) {
            if (($deviceStates[$id]['rtt'] ?? 0.0) > $limit) {
                return true;
            }
        }

        return false;
    }

    private function severityFor(array $ids, array $deviceStates, bool $linkDown): string
    {
        if ($linkDown) {
            return 'critical';
        }

        foreach ($ids as $id) {
            if (in_array($deviceStates[$id]['status'], ['down', 'critical'], true)) {
                return 'critical';
            }
        }

        return 'warning';
    }

    private function statusFromScore(int|float $score): string
    {
        return $score >= 90 ? 'healthy' : ($score >= 60 ? 'degraded' : 'critical');
    }

    private function snapshot(object $unit, Collection $devices, Collection $links, array $deviceStates, array $linkStates, ?int $unitScore, string $unitStatus, Carbon $now): void
    {
        $stamp = $now->toIso8601String();

        $rows = [[
            'unit_id' => $unit->id,
            'device_id' => null,
            'link_id' => null,
            'score' => $unitScore,
            'status' => $unitStatus,
            'calculated_at' => $stamp,
        ]];

        foreach ($devices as $device) {
            $rows[] = [
                'unit_id' => $unit->id,
                'device_id' => $device->id,
                'link_id' => null,
                'score' => $deviceStates[$device->id]['score'],
                'status' => $deviceStates[$device->id]['status'],
                'calculated_at' => $stamp,
            ];
        }

        foreach ($links as $link) {
            $rows[] = [
                'unit_id' => $unit->id,
                'device_id' => null,
                'link_id' => $link->id,
                'score' => $linkStates[$link->id]['score'],
                'status' => $linkStates[$link->id]['status'],
                'calculated_at' => $stamp,
            ];
        }

        DB::table('health_snapshots')->insert($rows);
    }

    private function persist(object $unit, array $findings, Carbon $now): void
    {
        $stamp = $now->toIso8601String();
        $seen = [];

        foreach ($findings as $finding) {
            $query = DB::table('incidents')
                ->where('unit_id', $unit->id)
                ->where('status', 'open')
                ->where('cause', $finding['cause']);

            if ($finding['root_device_id'] === null) {
                $query->whereNull('root_device_id');
            } else {
                $query->where('root_device_id', $finding['root_device_id']);
            }

            $existing = $query->first();

            $payload = [
                'severity' => $finding['severity'],
                'link_id' => $finding['link_id'],
                'affected_device_ids' => json_encode($finding['affected']),
                'evidence' => json_encode($finding['evidence']),
                'last_seen_at' => $stamp,
                'updated_at' => $stamp,
            ];

            if ($existing) {
                DB::table('incidents')->where('id', $existing->id)->update($payload);
                $seen[] = $existing->id;

                continue;
            }

            $seen[] = DB::table('incidents')->insertGetId($payload + [
                'unit_id' => $unit->id,
                'cause' => $finding['cause'],
                'root_device_id' => $finding['root_device_id'],
                'status' => 'open',
                'started_at' => $stamp,
                'created_at' => $stamp,
            ]);

            $this->matchFault($unit, $finding, $now);
        }

        DB::table('incidents')
            ->where('unit_id', $unit->id)
            ->where('status', 'open')
            ->whereNotIn('id', $seen)
            ->where('last_seen_at', '<', $now->copy()->subSeconds((int) config('multiguard.resolve_after_seconds'))->toIso8601String())
            ->update(['status' => 'resolved', 'resolved_at' => $stamp, 'updated_at' => $stamp]);
    }

    private function matchFault(object $unit, array $finding, Carbon $now): void
    {
        if ($finding['root_device_id'] === null) {
            return;
        }

        $fault = DB::table('fault_injections')
            ->where('unit_id', $unit->id)
            ->where('expected_device_id', $finding['root_device_id'])
            ->whereNull('detected_at')
            ->where('started_at', '<=', $now->toIso8601String())
            ->where('ended_at', '>=', $now->copy()->subSeconds(30)->toIso8601String())
            ->orderByDesc('started_at')
            ->first();

        if ($fault) {
            DB::table('fault_injections')->where('id', $fault->id)->update([
                'detected_at' => $now->toIso8601String(),
                'detected_cause' => $finding['cause'],
                'updated_at' => $now->toIso8601String(),
            ]);
        }
    }
}