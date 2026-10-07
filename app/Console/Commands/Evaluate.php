<?php
/**
 * Executa o motor de diagnostico para a unidade. Sem opcoes faz uma avaliacao e termina; com --loop repete
 * a cada --interval segundos (usado no laboratorio enquanto nao ha um servico agendado). Cada linha mostra
 * o indice de saude da unidade e as causas encontradas naquele ciclo.
 */

namespace App\Console\Commands;

use App\Services\DiagnosisEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Evaluate extends Command
{
    protected $signature = 'mg:evaluate {unit=unidade-teste} {--loop} {--interval=10}';

    protected $description = 'Avalia a saude e diagnostica a causa provavel de uma unidade';

    public function handle(DiagnosisEngine $engine): int
    {
        $unit = DB::table('units')->where('slug', $this->argument('unit'))->first();

        if (! $unit) {
            $this->error('Unidade não encontrada.');

            return self::FAILURE;
        }

        do {
            $result = $engine->evaluate($unit);

            $this->line(sprintf(
                '[%s] saúde %s (%s) | %s',
                now($unit->timezone)->format('H:i:s'),
                $result['score'] ?? '--',
                $result['status'],
                $result['findings'] === [] ? 'sem incidentes' : implode(', ', $result['findings'])
            ));

            if (! $this->option('loop')) {
                break;
            }

            sleep(max(1, (int) $this->option('interval')));
        } while (true);

        return self::SUCCESS;
    }
}