<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->boolean('es_persona_facturacion')->default(false)->after('id_usuario');

            $table->unsignedBigInteger('persona_facturacion_id')->nullable()->after('es_persona_facturacion');
            $table->foreign('persona_facturacion_id')
                ->references('id_persona')
                ->on('personas')
                ->nullOnDelete();

            $table->index('persona_facturacion_id', 'idx_personas_persona_facturacion_id');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropForeign(['persona_facturacion_id']);
            $table->dropIndex('idx_personas_persona_facturacion_id');
            $table->dropColumn(['es_persona_facturacion', 'persona_facturacion_id']);
        });
    }
};
