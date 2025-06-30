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
        Schema::create('espacios_configuracion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_espacio')->constrained('espacios');
            $table->date('fecha')->nullable();
            $table->integer('dia_semana')->nullable();
            $table->integer('minutos_uso');
            $table->integer('dias_previos_apertura');
            $table->time('hora_apertura');
            $table->integer('tiempo_cancelacion');
            $table->foreignId('creado_por')->constrained('usuarios', 'id_usuario');
            $table->foreignId('eliminado_por')->nullable()->constrained('usuarios', 'id_usuario');
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes('eliminado_en');

            $table->unique(['id_espacio', 'fecha']);
            $table->unique(['id_espacio', 'dia_semana']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('espacios_configuracion');
    }
};
