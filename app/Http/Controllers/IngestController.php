<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngestController extends Controller
{
    public function __invoke(Request $request)
    {
        $unit = $request->attributes->get('unit');

        $data = $request->validate([
            'points' => ['required', 'array', 'min:1', 'max:1000'],
            'points.*.metric' => ['required', 'string', 'max:60'],
            'points.*.source' => ['required', 'in:agent,snmp,browser'],
            'points.*.key' => ['nullable', 'string', 'max:120'],
            'points.*.device_id' => ['nullable', 'integer'],
            'points.*.link_id' => ['nullable', 'integer'],
            'points.*.time' => ['required', 'date', 'after:-14 days', 'before:+5 minutes'],
            'points.*.value' => ['required', 'numeric'],
        ]);

        $points = collect($data['points']);

        // device_id e link_id precisam pertencer à unidade autenticada
        foreach (['device_id' => 'devices', 'link_id' => 'links'] as $field => $table) {
            $ids = $points->pluck($field)->filter()->unique();
            if ($ids->isNotEmpty()) {
                $found = DB::table($table)
                    ->where('unit_id', $unit->id)
                    ->whereIn('id', $ids)
                    ->count();

                abort_if($found !== $ids->count(), 422, "$field inválido para esta unidade");
            }
        }

        // 1) Identidades únicas de séries no lote
        $ident = fn (array $p) => [
            'metric' => $p['metric'],
            'source' => $p['source'],
            'key' => $p['key'] ?? '',
            'device_id' => $p['device_id'] ?? null,
            'link_id' => $p['link_id'] ?? null,
        ];

        $identities = $points->map($ident)->unique(fn ($i) => implode('|', $i))->values();

        // 2) Insere séries que ainda não existem (idempotente)
        DB::table('series')->insertOrIgnore(
            $identities->map(fn ($i) => $i + [
                'unit_id' => $unit->id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );

        // 3) Mapeia identidade -> id da série
        $seriesIds = DB::table('series')
            ->where('unit_id', $unit->id)
            ->whereIn('metric', $identities->pluck('metric')->unique())
            ->get()
            ->mapWithKeys(fn ($s) => [
                implode('|', [$s->metric, $s->source, $s->key, $s->device_id, $s->link_id]) => $s->id,
            ]);

        // 4) Prepara linhas e grava métricas em lote (idempotente)
        $rows = $points->map(fn ($p) => [
            'time' => Carbon::parse($p['time'])->utc()->toIso8601String(),
            'series_id' => $seriesIds[implode('|', $ident($p))],
            'value' => $p['value'],
        ])->all();

        $stored = DB::table('metrics')->insertOrIgnore($rows);

        return response()->json([
            'received' => count($rows),
            'stored' => $stored,
        ], 202);
    }
}
