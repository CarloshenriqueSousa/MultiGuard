<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricQueryController extends Controller
{
    /**
     * Consulta pontos de uma métrica em um intervalo de tempo
     */
    public function query(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unit' => ['required', 'string'],
            'metric' => ['required', 'string'],
            'source' => ['nullable', 'string'],
            'key' => ['nullable', 'string'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:5000'],
        ]);

        $unit = Unit::where('slug', $validated['unit'])->first();
        if (! $unit) {
            return response()->json(['error' => 'Unidade não encontrada'], 404);
        }

        $minutes = (int) ($validated['minutes'] ?? 15);
        $since = Carbon::now()->subMinutes($minutes);
        $limit = (int) ($validated['limit'] ?? 1000);

        $query = DB::table('metrics')
            ->join('series', 'metrics.series_id', '=', 'series.id')
            ->where('series.unit_id', $unit->id)
            ->where('series.metric', $validated['metric'])
            ->where('metrics.time', '>=', $since)
            ->orderBy('metrics.time', 'asc')
            ->limit($limit);

        if (! empty($validated['source'])) {
            $query->where('series.source', $validated['source']);
        }

        if (isset($validated['key'])) {
            $query->where('series.key', $validated['key']);
        }

        $points = $query->select('metrics.time', 'metrics.value')->get();

        $timestamps = [];
        $values = [];

        foreach ($points as $p) {
            $timestamps[] = Carbon::parse($p->time)->timestamp;
            $values[] = (float) $p->value;
        }

        return response()->json([
            'unit' => $unit->slug,
            'metric' => $validated['metric'],
            'points_count' => count($points),
            'since' => $since->toIso8601String(),
            'data' => [
                'timestamps' => $timestamps,
                'values' => $values,
            ],
            'raw' => $points,
        ]);
    }
}
