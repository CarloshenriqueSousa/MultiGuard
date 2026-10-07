<?php
/**
 * Recria incidents (agrupados por causa raiz e dispositivo raiz) e health_snapshots (indice de saude 0-100
 * por dispositivo, enlace e unidade) e acrescenta detected_cause ao gabarito fault_injections. As tabelas
 * de uma versao anterior do motor sao descartadas. Em health_snapshots, device_id e link_id nulos indicam
 * a linha da unidade inteira.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('health_snapshots');

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('cause', 30);
            $table->foreignId('root_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('link_id')->nullable()->constrained('links')->nullOnDelete();
            $table->string('severity', 10);
            $table->string('status', 10)->default('open');
            $table->jsonb('affected_device_ids')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['unit_id', 'status']);
        });

        Schema::create('health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('score')->nullable();
            $table->string('status', 12);
            $table->timestampTz('calculated_at');

            $table->index(['unit_id', 'calculated_at']);
        });

        Schema::table('fault_injections', function (Blueprint $table) {
            $table->string('detected_cause', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fault_injections', function (Blueprint $table) {
            $table->dropColumn('detected_cause');
        });

        Schema::dropIfExists('health_snapshots');
        Schema::dropIfExists('incidents');
    }
};