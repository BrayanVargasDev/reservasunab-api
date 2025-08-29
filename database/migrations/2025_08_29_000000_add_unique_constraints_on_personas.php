<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Única persona por usuario (cuando id_usuario no es NULL)
        DB::statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS personas_unq_id_usuario_not_null ON personas (id_usuario) WHERE id_usuario IS NOT NULL"
        );

        // Única persona de facturación por titular (cuando es_persona_facturacion es true)
        DB::statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS personas_unq_facturacion_por_titular ON personas (persona_facturacion_id) WHERE es_persona_facturacion = true AND persona_facturacion_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS personas_unq_id_usuario_not_null");
        DB::statement("DROP INDEX IF EXISTS personas_unq_facturacion_por_titular");
    }
};
