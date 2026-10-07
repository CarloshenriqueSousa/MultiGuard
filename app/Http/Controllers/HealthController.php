<?php
/**
 * Expoe o ultimo estado de saude calculado pelo motor (unidade, dispositivos e enlaces) e os incidentes
 * abertos. Somente leitura: quem calcula e grava e o comando mg:evaluate. Sem snapshot nos ultimos 5 minutos
 * o campo health vem nulo, indicando que o avaliador esta parado.
 */

namespace App\Http\Controllers;

use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function show(string $unitSlug): JsonResponse
    {
        $unit = Unit::where('slug', $unitSlug)->first();

        if (! $unit) {
            return response()->json(['error' => 'Unidade não encontrada'], 404);
        }

        $rows = collect(DB::select(
            "SELECT DISTINCT ON (device_id, link_id) device_id, link_id, score, status, calculated_at
             FROM health_snapshots
             WHERE unit_id = ? AND calculated_at > now() - interval '5 minutes'
             ORDER BY device_id, link_id, calculated_at DESC",
            [$unit->id]
        ));

        $summary = $rows->first(fn ($row) => $row->device_id === null && $row->link_id === null);

        $incidents = DB::table('incidents')
            ->where('unit_id', $unit->id)
            ->where('status', 'open')
            ->orderByDesc('started_at')
            ->get()
            ->map(function ($incident) {
                $incident->affected_device_ids = json_decode($incident->affected_device_ids, true);
                $incident->evidence = json_decode($incident->evidence, true);

                return $incident;
            });

        return response()->json([
            'unit' => ['name' => $unit->name, 'slug' => $unit->slug],
            'evaluated_at' => $summary ? Carbon::parse($summary->calculated_at)->toIso8601String() : null,
            'health' => $summary ? ['score' => $summary->score, 'status' => $summary->status] : null,
            'devices' => $rows->filter(fn ($row) => $row->device_id !== null)->values(),
            'links' => $rows->filter(fn ($row) => $row->link_id !== null)->values(),
            'incidents' => $incidents,
        ]);
    }
}