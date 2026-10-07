<?php
/**
 * Semeia a topologia de laboratorio da unidade-teste: 1 gateway, 1 core, 1 servidor,
 * 2 distribuicoes e 4 acessos, com 8 enlaces e posicoes fixas por camada (y = altura no mapa).
 * Recria os dispositivos da unidade a cada execucao, entao pode ser rodado varias vezes.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LabTopologySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $tenantId = DB::table('tenants')->where('slug', 'teste')->value('id')
            ?? DB::table('tenants')->insertGetId([
                'name' => 'Teste',
                'slug' => 'teste',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $unitId = DB::table('units')->where('slug', 'unidade-teste')->value('id')
            ?? DB::table('units')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => 'Unidade Teste',
                'slug' => 'unidade-teste',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('devices')->where('unit_id', $unitId)->delete();

        $devices = [
            ['GW-01', 'gateway', 'router', '10.10.0.1', [0, 4, 0]],
            ['CORE-01', 'core', 'switch', '10.10.0.2', [0, 3, 0]],
            ['SRV-01', 'server', 'server', '10.10.0.10', [6, 3, 0]],
            ['DIST-A', 'distribution', 'switch', '10.10.1.1', [-4, 2, 0]],
            ['DIST-B', 'distribution', 'switch', '10.10.2.1', [4, 2, 0]],
            ['ACC-A1', 'access', 'switch', '10.10.1.11', [-6, 1, 0]],
            ['ACC-A2', 'access', 'switch', '10.10.1.12', [-2, 1, 0]],
            ['ACC-B1', 'access', 'switch', '10.10.2.11', [2, 1, 0]],
            ['ACC-B2', 'access', 'switch', '10.10.2.12', [6, 1, 0]],
        ];

        $ids = [];
        foreach ($devices as [$name, $layer, $kind, $ip, [$x, $y, $z]]) {
            $ids[$name] = DB::table('devices')->insertGetId([
                'unit_id' => $unitId,
                'name' => $name,
                'layer' => $layer,
                'kind' => $kind,
                'ip' => $ip,
                'position' => json_encode(['x' => $x, 'y' => $y, 'z' => $z]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $links = [
            ['GW-01', 'CORE-01', 1000000000],
            ['CORE-01', 'SRV-01', 1000000000],
            ['CORE-01', 'DIST-A', 10000000000],
            ['CORE-01', 'DIST-B', 10000000000],
            ['DIST-A', 'ACC-A1', 1000000000],
            ['DIST-A', 'ACC-A2', 1000000000],
            ['DIST-B', 'ACC-B1', 1000000000],
            ['DIST-B', 'ACC-B2', 1000000000],
        ];

        foreach ($links as [$from, $to, $capacity]) {
            DB::table('links')->insert([
                'unit_id' => $unitId,
                'from_device_id' => $ids[$from],
                'to_device_id' => $ids[$to],
                'capacity_bps' => $capacity,
                'source' => 'manual',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}