<?php
/**
 * Lista os incidentes da unidade que tocam a janela pedida (abertos ou vistos pela ultima vez dentro dela),
 * com o dispositivo raiz, o conjunto afetado e o intervalo em segundos desde a epoca. O front usa esse intervalo
 * para desenhar as faixas de incidente sobre os graficos (annotations). O fim do intervalo e last_seen_at, a
 * ultima evidencia registrada pelo motor, e nao o momento em que o incidente foi encerrado.
 */

namespace App\Http\Controllers;

use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncidentController extends Controller
{
    public function index(Request $request, string $unitSlug): JsonResponse
    {
        $unit = Unit::where('slug', $unitSlug)->first();

        if (! $unit) {
            return response()->json(['error' => 'Unidade não encontrada'], 404);
        }

        $data = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $since = now()->subMinutes((int) ($data['minutes'] ?? 60));

        $incidents = DB::table('incidents as i')
            ->leftJoin('devices as d', 'd.id', '=', 'i.root_device_id')
            ->where('i.unit_id', $unit->id)
            ->where(function ($query) use ($since) {
                $query->where('i.status', 'open')
                    ->orWhere('i.last_seen_at', '>=', $since->toIso8601String());
            })
            ->orderByDesc('i.started_at')
            ->select(
                'i.id', 'i.cause', 'i.severity', 'i.status', 'i.root_device_id', 'd.name as root_device',
                'i.link_id', 'i.affected_device_ids', 'i.evidence', 'i.started_at', 'i.last_seen_at'
            )
            ->get()
            ->map(function ($incident) {
                $started = Carbon::parse($incident->started_at);
                $ended = Carbon::parse($incident->last_seen_at);

                return [
                    'id' => $incident->id,
                    'cause' => $incident->cause,
                    'severity' => $incident->severity,
                    'status' => $incident->status,
                    'root_device_id' => $incident->root_device_id,
                    'root_device' => $incident->root_device,
                    'link_id' => $incident->link_id,
                    'affected_device_ids' => json_decode($incident->affected_device_ids ?? '[]', true),
                    'evidence' => json_decode($incident->evidence ?? 'null', true),
                    'started_at' => $started->toIso8601String(),
                    'started_ts' => $started->timestamp,
                    'ended_ts' => $ended->timestamp,
                ];
            });

        return response()->json(['incidents' => $incidents]);
    }
}