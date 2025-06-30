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
        Schema::create('espacio_tipo_usuario_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_espacio')->constrained('espacios')->onDelete('cascade');
            $table->enum('tipo_usuario', ['estudiante', 'administrativo', 'egresado', 'externo']);
            $table->float('porcentaje_descuento')->default(0);
            $table->integer('retraso_reserva')->default(0);
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent()->useCurrentOnUpdate();
            $table->foreignId('creado_por')->constrained('usuarios', 'id_usuario');
            $table->foreignId('actualizado_por')->nullable()->constrained('usuarios', 'id_usuario');
            $table->foreignId('eliminado_por')->nullable()->constrained('usuarios', 'id_usuario');
            $table->softDeletes('eliminado_en');

            $table->unique(['id_espacio', 'tipo_usuario'], 'unique_espacio_tipo_usuario');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('espacio_tipo_usuario_configs');
    }
};
