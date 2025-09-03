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
        Schema::create('espacios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->boolean('agregar_jugadores')->default(false);
            $table->integer('minimo_jugadores')->nullable();
            $table->integer('maximo_jugadores')->nullable();
            $table->boolean('permite_externos')->default(false);
            $table->foreignId('id_sede')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('id_categoria')->nullable()->constrained('categorias')->nullOnDelete();
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
        Schema::dropIfExists('espacios');
    }
};
