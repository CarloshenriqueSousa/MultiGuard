<?php
/**
 * Devolve o ultimo valor conhecido de cada metrica de cada dispositivo e enlace da unidade, olhando so os
 * ultimos 30 segundos, separados em devices e links e indexados pelo id do alvo. O mapa 3D usa estes valores
 * para a espessura do enlace (utilizacao) e o halo de perda. Alvo que parou de enviar simplesmente nao aparece,
 * e o front trata a ausencia como falta de dado, nunca como valor zero.
 */

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LatestController extends Controller
{
    private const METRICS = [
        'ping_loss_pct',
        'ping_rtt_ms',
        'ping_jitter_ms',
        'if_oper_status',
        'if_util_pct',
        'if_errors_per_min',
    ];

    public function __invoke(string $unitSlug): JsonResponse
    {
        $unit = Unit::where('slug', $unitSlug)->first();

        if (! $unit) {
            return response()->json(['error' => 'Unidade não encontrada'], 404);
        }

        $placeholders = implode(',', array_fill(0, count(self::METRICS), '?'));

        $rows = DB::select(
            "SELECT DISTINCT ON (s.id) s.device_id, s.link_id, s.metric, m.value
             FROM metrics m
             JOIN series s ON s.id = m.series_id
             WHERE s.unit_id = ? AND m.time >= now() - interval '30 seconds' AND s.metric IN ($placeholders)
             ORDER BY s.id, m.time DESC",
            [$unit->id, ...self::METRICS]
        );

        $devices = [];
        $links = [];

        foreach ($rows as $row) {
            $value = round((float) $row->value, 2);

            if ($row->device_id !== null) {
                $devices[$row->device_id][$row->metric] = $value;
            } elseif ($row->link_id !== null) {
                $links[$row->link_id][$row->metric] = $value;
            }
        }

        return response()->json([
            'sampled_at' => now()->toIso8601String(),
            'devices' => (object) $devices,
            'links' => (object) $links,
        ]);
    }
}
