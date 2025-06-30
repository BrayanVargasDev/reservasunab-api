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
        Schema::create('espacios_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_espacio')->constrained('espacios')->onDelete('cascade');
            $table->text('codigo');
            $table->string('titulo');
            $table->string('url');
            $table->string('ubicacion');
            $table->timestamp('creado_en')->useCurrent();

            $table->unique(['id_espacio', 'codigo']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('espacios_imagenes');
    }
};
