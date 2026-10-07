<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fault_injections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->foreignId('link_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('expected_cause', 30);
            $table->foreignId('expected_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at');
            $table->timestampTz('detected_at')->nullable();
            $table->timestampsTz();

            $table->index(['unit_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fault_injections');
    }
};
