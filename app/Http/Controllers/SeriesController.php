<?php
/**
 * Consulta uma serie temporal de um dispositivo ou de um enlace da unidade, agregada em baldes de tempo
 * (media por balde) para manter cerca de 300 pontos por grafico em qualquer periodo. O alvo precisa pertencer
 * a unidade informada. A resposta vem em dois vetores paralelos (timestamps em segundos e valores), formato
 * que o uPlot aceita sem conversao.
 */

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeriesController extends Controller
{
    public function __invoke(Request $request, string $unitSlug): JsonResponse
    {
        $unit = Unit::where('slug', $unitSlug)->first();

        if (! $unit) {
            return response()->json(['error' => 'Unidade não encontrada'], 404);
        }

        $data = $request->validate([
            'metric' => ['required', 'string', 'max:60'],
            'device_id' => ['nullable', 'integer', 'required_without:link_id'],
            'link_id' => ['nullable', 'integer', 'required_without:device_id'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $deviceId = $data['device_id'] ?? null;
        $linkId = $data['link_id'] ?? null;

        if ($deviceId !== null && $linkId !== null) {
            return response()->json(['error' => 'Informe device_id ou link_id, não os dois'], 422);
        }

        $column = $deviceId !== null ? 'device_id' : 'link_id';
        $table = $deviceId !== null ? 'devices' : 'links';
        $targetId = $deviceId ?? $linkId;

        $exists = DB::table($table)->where('unit_id', $unit->id)->where('id', $targetId)->exists();

        if (! $exists) {
            return response()->json(['error' => 'Alvo não encontrado na unidade'], 404);
        }

        $minutes = (int) ($data['minutes'] ?? 15);
        $bucket = max(5, (int) ceil($minutes * 60 / 300));

        $rows = DB::select(
            'SELECT extract(epoch FROM time_bucket(make_interval(secs => ?), m.time))::bigint AS ts,
                    avg(m.value) AS value
             FROM metrics m
             JOIN series s ON s.id = m.series_id
             WHERE s.unit_id = ? AND s.metric = ? AND s.'.$column.' = ?
               AND m.time >= now() - make_interval(mins => ?)
             GROUP BY ts
             ORDER BY ts',
            [$bucket, $unit->id, $data['metric'], $targetId, $minutes]
        );

        return response()->json([
            'metric' => $data['metric'],
            'minutes' => $minutes,
            'bucket_seconds' => $bucket,
            'data' => [
                'timestamps' => array_map(fn ($row) => (int) $row->ts, $rows),
                'values' => array_map(fn ($row) => round((float) $row->value, 2), $rows),
            ],
        ]);
    }
}