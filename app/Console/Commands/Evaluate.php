<?php
/**
 * Executa o motor de diagnostico para a unidade. Sem opcoes faz uma avaliacao e termina. Com --loop repete a
 * cada --interval segundos ate ser interrompido; com --for=N repete por no maximo N segundos e termina, formato
 * usado pelo agendador do Laravel, que dispara este comando uma vez por minuto (o agendador do Laravel 10 nao
 * executa tarefas em intervalo menor que um minuto). Cada linha mostra o indice de saude da unidade e as causas
 * encontradas naquele ciclo.
 */

namespace App\Console\Commands;

use App\Services\DiagnosisEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Evaluate extends Command
{
    protected $signature = 'mg:evaluate {unit=unidade-teste} {--loop} {--interval=10} {--for=0}';

    protected $description = 'Avalia a saude e diagnostica a causa provavel de uma unidade';

    public function handle(DiagnosisEngine $engine): int
    {
        $unit = DB::table('units')->where('slug', $this->argument('unit'))->first();

        if (! $unit) {
            $this->error('Unidade não encontrada.');

            return self::FAILURE;
        }

        $interval = max(1, (int) $this->option('interval'));
        $limit = (int) $this->option('for');
        $repeat = $limit > 0 || $this->option('loop');
        $deadline = $limit > 0 ? microtime(true) + $limit : null;

        do {
            $result = $engine->evaluate($unit);

            $this->line(sprintf(
                '[%s] saúde %s (%s) | %s',
                now($unit->timezone)->format('H:i:s'),
                $result['score'] ?? '--',
                $result['status'],
                $result['findings'] === [] ? 'sem incidentes' : implode(', ', $result['findings'])
            ));

            if (! $repeat) {
                break;
            }

            if ($deadline !== null && microtime(true) + $interval > $deadline) {
                break;
            }

            sleep($interval);
        } while (true);

        return self::SUCCESS;
    }
}
