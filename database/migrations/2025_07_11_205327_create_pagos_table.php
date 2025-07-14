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

        // de la pasarela los estados son: creado, pendiente, completado, fallido, cancelado
        Schema::create('pagos', function (Blueprint $table) {
            $table->ulid('codigo', 26)->primary();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->foreignId('id_reserva')->constrained('reservas');
            $table->decimal('valor', 15, 2)->default(0.00);
            $table->string('estado')->default('inicial');
            $table->string('url_ecollect')->nullable();
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent()->nullable();
            $table->softDeletes('eliminado_en');
        });

        //Indice para el ticket_id
        Schema::table('pagos', function (Blueprint $table) {
            $table->index('ticket_id');
            $table->index('estado');
            $table->index('creado_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
