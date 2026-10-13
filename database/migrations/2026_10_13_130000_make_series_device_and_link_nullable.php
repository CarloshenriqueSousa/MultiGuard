<?php
/**
 * Torna as colunas device_id e link_id opcionais na tabela series para suportar metricas de dispositivos, enlaces ou sinteticas.
 */

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE series ALTER COLUMN device_id DROP NOT NULL;');
        DB::statement('ALTER TABLE series ALTER COLUMN link_id DROP NOT NULL;');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE series ALTER COLUMN device_id SET NOT NULL;');
        DB::statement('ALTER TABLE series ALTER COLUMN link_id SET NOT NULL;');
    }
};
