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
        Schema::create('franjas_horarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_config')->constrained('espacios_configuracion')->onDelete('cascade');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->decimal('valor', 10, 0);
            $table->boolean('activa');
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
        Schema::dropIfExists('franjas_horarias');
    }
};
