<?php
/**
 * Registra uma falha de laboratorio na tabela fault_injections, que serve de gabarito para medir
 * deteccao (K1) e localizacao da causa (K3). Para falhas de enlace, o alvo e o dispositivo da ponta
 * de baixo (o uplink do DIST-A e o enlace que liga o CORE-01 ao DIST-A). A causa esperada segue as
 * regras do documento: varios dispositivos atras do enlace indicam uplink, um unico indica falha local.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FaultInject extends Command
{
    protected $signature = 'mg:fault-inject
        {kind : link_down, port_errors, congestion ou device_down}
        {target : nome do dispositivo alvo}
        {--duration=120 : duracao em segundos}
        {--unit=unidade-teste : slug da unidade}';

    protected $description = 'Injeta uma falha de laboratorio e registra o gabarito';

    public function handle(): int
    {
        $kind = $this->argument('kind');

        if (! in_array($kind, ['link_down', 'port_errors', 'congestion', 'device_down'], true)) {
            $this->error('Tipo invalido. Use: link_down, port_errors, congestion ou device_down.');

            return self::FAILURE;
        }

        $unit = DB::table('units')->where('slug', $this->option('unit'))->first();

        if (! $unit) {
            $this->error('Unidade não encontrada.');

            return self::FAILURE;
        }

        $device = DB::table('devices')
            ->where('unit_id', $unit->id)
            ->where('name', strtoupper($this->argument('target')))
            ->first();

        if (! $device) {
            $this->error('Dispositivo não encontrado na unidade.');

            return self::FAILURE;
        }

        $linkId = null;

        if ($kind !== 'device_down') {
            $link = DB::table('links')
                ->where('unit_id', $unit->id)
                ->where('to_device_id', $device->id)
                ->first();

            if (! $link) {
                $this->error("{$device->name} não tem enlace de subida.");

                return self::FAILURE;
            }

            $linkId = $link->id;
        }

        $hasChildren = DB::table('links')->where('from_device_id', $device->id)->exists();

        $cause = match ($kind) {
            'link_down' => $hasChildren ? 'uplink' : 'local_device',
            'port_errors' => $hasChildren ? 'uplink' : 'cable_port',
            'congestion' => 'congestion',
            'device_down' => 'local_device',
        };

        $start = now();
        $end = now()->addSeconds(max(10, (int) $this->option('duration')));

        $id = DB::table('fault_injections')->insertGetId([
            'unit_id' => $unit->id,
            'kind' => $kind,
            'link_id' => $linkId,
            'device_id' => $kind === 'device_down' ? $device->id : null,
            'expected_cause' => $cause,
            'expected_device_id' => $device->id,
            'started_at' => $start->toIso8601String(),
            'ended_at' => $end->toIso8601String(),
            'created_at' => $start->toIso8601String(),
            'updated_at' => $start->toIso8601String(),
        ]);

        $this->table(
            ['Falha', 'Tipo', 'Alvo', 'Causa esperada', 'Até'],
            [[$id, $kind, $device->name, $cause, $end->setTimezone($unit->timezone)->format('H:i:s')]]
        );

        return self::SUCCESS;
    }
}