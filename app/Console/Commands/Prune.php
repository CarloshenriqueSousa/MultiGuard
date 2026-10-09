<?php
/**
 * Remove os snapshots de saude mais antigos que a retencao configurada (multiguard.snapshot_retention_days,
 * 7 dias por padrao). Sem isso a tabela health_snapshots cresce sem limite. Incidentes e o gabarito de falhas
 * nao sao apagados. Roda de hora em hora pelo agendador.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Prune extends Command
{
    protected $signature = 'mg:prune';

    protected $description = 'Apaga snapshots de saude fora da retencao';

    public function handle(): int
    {
        $days = max(1, (int) config('multiguard.snapshot_retention_days'));

        $deleted = DB::table('health_snapshots')
            ->where('calculated_at', '<', now()->subDays($days)->toIso8601String())
            ->delete();

        $this->info("Snapshots removidos: {$deleted} (retenção de {$days} dias)");

        return self::SUCCESS;
    }
}
