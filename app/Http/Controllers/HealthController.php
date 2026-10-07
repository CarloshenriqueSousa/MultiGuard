<?php
/**
 * Controller responsavel por expor o status de saude e incidentes da unidade.
 */

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Services\HealthAndIncidentEngine;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function show(string $unitSlug, HealthAndIncidentEngine $engine): JsonResponse
    {
        $unit = Unit::where('slug', $unitSlug)->first();

        if (! $unit) {
            return response()->json(['error' => 'Unidade não encontrada'], 404);
        }

        $evaluation = $engine->evaluateUnit($unit);

        return response()->json([
            'unit' => [
                'name' => $unit->name,
                'slug' => $unit->slug,
            ],
            'health' => [
                'score' => $evaluation['score'],
                'status' => $evaluation['status'],
                'metrics' => $evaluation['metrics'],
            ],
            'active_incident' => $evaluation['incident'],
        ]);
    }
}
