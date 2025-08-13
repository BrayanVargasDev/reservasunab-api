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
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario');
            $table->string('token_hash', 128)->unique();
            $table->string('dispositivo')->nullable();
            $table->string('ip')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('expira_en')->nullable();
            $table->timestamp('revocado_en')->nullable();
            $table->string('reemplazado_por_token_hash')->nullable();
            $table->index('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
