<?php
/**
 * Cria as tabelas de historico de indices de saude e incidentes com causa provavel.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->enum('status', ['healthy', 'degraded', 'critical']);
            $table->decimal('avg_rtt', 8, 2);
            $table->decimal('avg_jitter', 8, 2);
            $table->decimal('packet_loss', 5, 2);
            $table->timestampTz('calculated_at');
            $table->timestampsTz();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('root_cause');
            $table->enum('severity', ['warning', 'critical']);
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->text('summary');
            $table->timestampTz('started_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('health_snapshots');
    }
};
