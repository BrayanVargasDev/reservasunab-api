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
        Schema::create('pagos_detalles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_pago', 26);
            $table->enum('tipo_concepto', ['reserva', 'elemento', 'mensualidad']);
            $table->unsignedInteger('cantidad');
            $table->unsignedBigInteger('id_concepto');
            $table->decimal('total', 12, 2);

            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes('eliminado_en');

            $table->foreign('id_pago')->references('codigo')->on('pagos')->cascadeOnDelete();
            $table->index(['tipo_concepto', 'id_concepto']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos_detalles');
    }
};
