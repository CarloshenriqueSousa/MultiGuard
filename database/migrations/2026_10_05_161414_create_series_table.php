<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 60);
            $table->string('source', 20);
            $table->string('key', 120)->default('');
            $table->json('labels')->nullable();
            $table->timestampsTz();
        });
        DB::statement("
            CREATE UNIQUE INDEX series_identity ON series
            (unit_id, metric, source, key, COALESCE(device_id, 0), COALESCE(link_id, 0))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
