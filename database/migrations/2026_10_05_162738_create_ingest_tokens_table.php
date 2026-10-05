<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    /**
     * O token em sí nunca vai ser armazenado, apenas o hash SHA256 dele.
     * Se o banco de algum modo for vazado, o token não poderá ser recuperado ou não poderá ser utilizado.
     * Usamos SHA-256 e não bcrypt porque o token é aleatório e longo (alta entropia), e a busca precisa ser direta por igualdade no índice.
    */
    public function up(): void
    {
        Schema::create('ingest_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingest_tokens');
    }
};
