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
        Schema::create('pago_consultas', function (Blueprint $table) {
            $table->string('codigo', 26)->primary();
            $table->float('valor_real', 15, 2)->nullable();
            $table->float('valor_transaccion', 15, 2);
            $table->string('estado', 50);
            $table->string('ticket_id', 50);
            $table->string('codigo_traza', 50);
            $table->string('medio_pago', 50);
            $table->string('tipo_doc_titular', 5);
            $table->string('numero_doc_titular', 50);
            $table->string('nombre_titular', 100);
            $table->string('email_titular', 100);
            $table->string('celular_titular', 20);
            $table->string('descripcion_pago');
            $table->string('nombre_medio_pago', 150);
            $table->string('tarjeta_oculta', 20)->nullable();
            $table->string('ultimos_cuatro', 4)->nullable();
            $table->timestamp('fecha_banco');
            $table->string('moneda', 10);
            $table->bigInteger('id_reserva');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('fecha_reserva', 20);
            $table->string('codigo_reserva', 50);
            $table->bigInteger('id_usuario_reserva');
            $table->string('tipo_doc_usuario_reserva', 5);
            $table->string('doc_usuario_reserva', 50);
            $table->string('email_usuario_reserva', 100);
            $table->string('celular_usuario_reserva', 20);
            $table->bigInteger('id_espacio');
            $table->string('nombre_espacio', 100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pago_consultas');
    }
};
