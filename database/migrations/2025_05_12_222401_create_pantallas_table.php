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
        Schema::create('pantallas', function (Blueprint $table) {
            $table->id('id_pantalla')->autoIncrement();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->string('codigo')->unique();
            $table->integer('orden')->default(0);
            $table->string('ruta')->nullable();
            $table->string('icono')->nullable();
            $table->boolean('visible')->default(true);
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->nullable();
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
        Schema::dropIfExists('pantallas');
    }
};
