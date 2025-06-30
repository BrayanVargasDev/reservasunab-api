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
        Schema::create('permisos', function (Blueprint $table) {
            $table->id('id_permiso');
            $table->string('nombre')->unique();
            $table->string('codigo')->unique();
            $table->string('icono')->nullable();
            $table->string('descripcion')->nullable();
            $table->foreignId('id_pantalla')->nullable()
                ->constrained('pantallas', 'id_pantalla')
                ->nullOnDelete();
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
        Schema::dropIfExists('permisos');
    }
};
