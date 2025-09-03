<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id('id_persona');
            $table->integer('tipo_documento_id')->nullable();
            $table->string('numero_documento', 30)->nullable()->unique();
            $table->string('primer_nombre', 50);
            $table->string('segundo_nombre', 50)->nullable();
            $table->string('primer_apellido', 50);
            $table->string('segundo_apellido', 50)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('direccion', 150)->nullable();
            $table->string('celular', 15)->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->nullable();

            $table->foreign('tipo_documento_id')->references('id_tipo')->on('tipos_documento');
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
            $table->unique(['tipo_documento_id', 'numero_documento'], 'uk_persona_documento');
        });
    }

    public function down()
    {
        Schema::dropIfExists('personas');
    }
};
