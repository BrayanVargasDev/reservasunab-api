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
        Schema::create('reservas_detalles', function (Blueprint $table) {
            $table->foreignId('id_reserva')->constrained('reservas', 'id')->cascadeOnDelete();
            $table->foreignId('id_elemento')->constrained('elementos', 'id')->cascadeOnDelete();
            $table->integer('cantidad');
            $table->primary(['id_reserva', 'id_elemento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservas_detalles');
    }
};
