<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TopologyController extends Controller
{
    /**
     * Retorna a topologia completa da unidade (nós e enlaces)
     */
    public function show(string $unitSlug): JsonResponse
    {
        $unit = Unit::where('slug', $unitSlug)->first();

        if (! $unit) {
            return response()->json(['error' => 'Unidade não encontrada'], 404);
        }

        $devices = DB::table('devices')
            ->where('unit_id', $unit->id)
            ->select('id', 'name', 'slug', 'type', 'layer', 'ip')
            ->orderBy('layer', 'desc')
            ->get();

        $links = DB::table('links')
            ->where('unit_id', $unit->id)
            ->select('id', 'name', 'source_device_id', 'target_device_id', 'speed_mbps')
            ->get();

        return response()->json([
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'slug' => $unit->slug,
            ],
            'nodes' => $devices,
            'edges' => $links,
        ]);
    }
}
