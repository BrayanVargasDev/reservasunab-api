<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTablePersonasAddFacturacionColumns extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->enum('tipo_persona', ['natural', 'juridica'])->default('natural')->after('celular');
            $table->foreignId('regimen_tributario_id')->nullable()->constrained('regimenes_tributarios', 'codigo')->nullOnDelete()->after('tipo_persona');
            $table->foreignId('ciudad_expedicion_id')->nullable()->constrained('ciudades')->nullOnDelete()->after('regimen_tributario_id');
            $table->foreignId('ciudad_residencia_id')->nullable()->constrained('ciudades')->nullOnDelete()->after('ciudad_expedicion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropForeign(['regimen_tributario_id']);
            $table->dropForeign(['ciudad_expedicion_id']);
            $table->dropForeign(['ciudad_residencia_id']);

            $table->dropColumn([
                'tipo_persona',
                'regimen_tributario_id',
                'ciudad_expedicion_id',
                'ciudad_residencia_id'
            ]);
        });
    }
}
