<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('espacios_novedades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_espacio')->constrained('espacios')->onDelete('cascade');
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('descripcion', 255)->nullable();
            $table->string('tipo', 50)->default('mantenimiento');
            $table->foreignId('creado_por')->constrained('usuarios', 'id_usuario');
            $table->foreignId('actualizado_por')->nullable()->constrained('usuarios', 'id_usuario');
            $table->foreignId('eliminado_por')->nullable()->constrained('usuarios', 'id_usuario');
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes('eliminado_en');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('espacios_novedades');
    }
};
