<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('elementos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre');
            $table->integer('cantidad');
            $table->decimal('valor_estudiante', 12, 2)->default(0);
            $table->decimal('valor_egresado', 12, 2)->default(0);
            $table->decimal('valor_administrativo', 12, 2)->default(0);
            $table->foreignId('id_espacio')->constrained('espacios', 'id')->cascadeOnDelete();

            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes('eliminado_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('elementos');
    }
};
