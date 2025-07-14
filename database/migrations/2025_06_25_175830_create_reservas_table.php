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
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $table->foreignId('id_espacio')->constrained('espacios');
            $table->dateTime('fecha');
            $table->foreignId('id_configuracion')->constrained('espacios_configuracion');
            $table->string('estado')->default('inicial');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->boolean('check_in')->default(false);

            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent()->nullable();
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
        Schema::dropIfExists('reservas');
    }
};
