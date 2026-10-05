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
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('to_device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('from_port')->nullable();
            $table->string('to_port')->nullable();
            $table->unsignedBigInteger('capacity_bps')->nullable();
            $table->string('source', 10)->default('manual');
            $table->timestampsTz();
            $table->index('from_device_id');
            $table->index('to_device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
