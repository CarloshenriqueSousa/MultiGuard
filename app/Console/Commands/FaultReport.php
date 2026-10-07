<?php
/**
 * Relatorio do gabarito de falhas: para cada falha injetada mostra se o motor detectou, em quantos segundos
 * e se a causa apontada bate com a esperada, e resume K1 (deteccao em ate 60 s) e K3 (acerto da causa).
 */

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FaultReport extends Command
{
    protected $signature = 'mg:fault-report {unit=unidade-teste}';

    protected $description = 'Compara as falhas injetadas com o diagnostico do motor (K1 e K3)';

    public function handle(): int
    {
        $unit = DB::table('units')->where('slug', $this->argument('unit'))->first();

        if (! $unit) {
            $this->error('Unidade não encontrada.');

            return self::FAILURE;
        }

        $faults = DB::table('fault_injections as f')
            ->leftJoin('devices as d', 'd.id', '=', 'f.expected_device_id')
            ->where('f.unit_id', $unit->id)
            ->orderBy('f.id')
            ->select('f.id', 'f.kind', 'f.expected_cause', 'f.detected_cause', 'f.started_at', 'f.detected_at', 'd.name as target')
            ->get();

        if ($faults->isEmpty()) {
            $this->warn('Nenhuma falha injetada ainda.');

            return self::SUCCESS;
        }

        $rows = [];
        $fast = 0;
        $correct = 0;

        foreach ($faults as $fault) {
            $seconds = null;
            $match = '-';

            if ($fault->detected_at !== null) {
                $seconds = Carbon::parse($fault->detected_at)->timestamp - Carbon::parse($fault->started_at)->timestamp;
                $match = $fault->detected_cause === $fault->expected_cause ? 'sim' : 'não';

                if ($seconds <= 60) {
                    $fast++;
                }

                if ($match === 'sim') {
                    $correct++;
                }
            }

            $rows[] = [
                $fault->id,
                $fault->kind,
                $fault->target ?? '-',
                $fault->expected_cause,
                $fault->detected_cause ?? '-',
                $seconds === null ? 'não detectada' : $seconds.' s',
                $match,
            ];
        }

        $this->table(['#', 'Falha', 'Alvo', 'Esperada', 'Apontada', 'Detecção', 'Causa ok'], $rows);

        $total = $faults->count();

        $this->line(sprintf('K1 detecção em até 60 s: %d/%d (%.0f%%)', $fast, $total, 100 * $fast / $total));
        $this->line(sprintf('K3 causa correta: %d/%d (%.0f%%)', $correct, $total, 100 * $correct / $total));

        return self::SUCCESS;
    }
}