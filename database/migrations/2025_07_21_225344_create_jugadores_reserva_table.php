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
        Schema::create('jugadores_reserva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_reserva')->constrained('reservas')->onDelete('cascade');
            // Referencia a usuario O beneficiario (uno de los dos debe estar presente)
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->unsignedBigInteger('id_beneficiario')->nullable();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->onDelete('cascade');
            $table->foreign('id_beneficiario')->references('id')->on('beneficiarios')->onDelete('cascade');
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminado_en')->nullable();

            // Índices
            $table->index(['id_reserva', 'id_usuario']);
            $table->index(['id_reserva', 'id_beneficiario']);
            // Restricción única para evitar duplicados por tipo de entidad
            $table->unique(['id_reserva', 'id_usuario'], 'unique_jugador_reserva_usuario');
            $table->unique(['id_reserva', 'id_beneficiario'], 'unique_jugador_reserva_beneficiario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jugadores_reserva');
    }
};
