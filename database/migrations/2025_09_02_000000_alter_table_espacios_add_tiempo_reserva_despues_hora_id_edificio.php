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
        Schema::table('espacios', function (Blueprint $table) {
            $table->integer('tiempo_limite_reserva')->nullable();
            $table->boolean('despues_hora')->default(false);
            $table->string('id_edificio', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('espacios', function (Blueprint $table) {
            $table->dropColumn(['tiempo_limite_reserva', 'despues_hora', 'id_edificio']);
        });
    }
};
