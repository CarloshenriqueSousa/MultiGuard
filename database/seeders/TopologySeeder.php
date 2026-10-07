<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TopologySeeder extends Seeder
{
    public function run(): void
    {
        $unit = Unit::where('slug', 'unidade-teste')->first();

        if (! $unit) {
            $this->command->error("Unidade 'unidade-teste' não encontrada.");
            return;
        }

        DB::table('links')->where('unit_id', $unit->id)->delete();
        DB::table('devices')->where('unit_id', $unit->id)->delete();

        $devices = [
            [
                'name' => 'Gateway de Borda (Firewall/Router)',
                'slug' => 'rtr-gw-01',
                'type' => 'gateway',
                'layer' => 4, // Topo / Saída
                'ip' => '192.168.1.1',
            ],
            [
                'name' => 'Switch Core Central',
                'slug' => 'sw-core-01',
                'type' => 'core',
                'layer' => 3,
                'ip' => '192.168.1.2',
            ],
            [
                'name' => 'Switch Distribuição Bloco A',
                'slug' => 'sw-dist-01',
                'type' => 'distribution',
                'layer' => 2,
                'ip' => '192.168.1.10',
            ],
            [
                'name' => 'Switch Acesso Recepção / Clínico',
                'slug' => 'sw-acc-01',
                'type' => 'access',
                'layer' => 1,
                'ip' => '192.168.1.20',
            ],
            [
                'name' => 'Servidor Local de Prontuário / Aplicação',
                'slug' => 'srv-app-01',
                'type' => 'server',
                'layer' => 2,
                'ip' => '192.168.1.50',
            ],
        ];

        $deviceIds = [];
        foreach ($devices as $d) {
            $deviceIds[$d['slug']] = DB::table('devices')->insertGetId([
                'unit_id' => $unit->id,
                'name' => $d['name'],
                'slug' => $d['slug'],
                'type' => $d['type'],
                'layer' => $d['layer'],
                'ip' => $d['ip'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $links = [
            [
                'source' => 'rtr-gw-01',
                'target' => 'sw-core-01',
                'name' => 'Uplink Gateway -> Core',
                'speed_mbps' => 1000,
            ],
            [
                'source' => 'sw-core-01',
                'target' => 'sw-dist-01',
                'name' => 'Enlace Core -> Distribuição Bloco A',
                'speed_mbps' => 1000,
            ],
            [
                'source' => 'sw-dist-01',
                'target' => 'sw-acc-01',
                'name' => 'Downlink Distribuição -> Acesso Recepção',
                'speed_mbps' => 100,
            ],
            [
                'source' => 'sw-core-01',
                'target' => 'srv-app-01',
                'name' => 'Enlace Core -> Servidor de Aplicação',
                'speed_mbps' => 1000,
            ],
        ];

        foreach ($links as $l) {
            DB::table('links')->insert([
                'unit_id' => $unit->id,
                'source_device_id' => $deviceIds[$l['source']],
                'target_device_id' => $deviceIds[$l['target']],
                'name' => $l['name'],
                'speed_mbps' => $l['speed_mbps'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Topologia cadastrada com sucesso para {$unit->name}: 5 dispositivos e 4 enlaces.");
    }
}
