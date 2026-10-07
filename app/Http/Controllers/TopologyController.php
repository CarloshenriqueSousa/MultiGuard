<?php
/**
 * Expoe a topologia de uma unidade (dispositivos e enlaces) para o console e para o mapa
 */

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TopologyController extends Controller
{
    public function show(string $unitSlug): JsonResponse
    {
        $unit = Unit::where('slug', $unitSlug)->first();

        if (! $unit) {
            return response()->json(['error' => 'Unidade não encontrada'], 404);
        }

        $nodes = DB::table('devices')
            ->where('unit_id', $unit->id)
            ->select('id', 'name', 'layer', 'kind', 'ip', 'position')
            ->orderBy('id')
            ->get()
            ->map(function ($device) {
                $device->position = json_decode($device->position, true);

                return $device;
            });

        $edges = DB::table('links')
            ->where('unit_id', $unit->id)
            ->select('id', 'from_device_id', 'to_device_id', 'capacity_bps')
            ->orderBy('id')
            ->get();

        return response()->json([
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'slug' => $unit->slug,
            ],
            'nodes' => $nodes,
            'edges' => $edges,
        ]);
    }
}