<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cambia el tipo de la columna ldap_uid de uuid a varchar(250).
     */
    public function up(): void
    {
        // Usamos SQL directo para evitar dependencia de doctrine/dbal
        DB::statement("ALTER TABLE usuarios ALTER COLUMN ldap_uid TYPE VARCHAR(50) USING ldap_uid::text");
    }

    /**
     * Revierte el cambio al tipo original uuid (puede fallar si se almacenaron valores que no sean UUID válidos tras el cambio).
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE usuarios ALTER COLUMN ldap_uid TYPE uuid USING ldap_uid::uuid");
    }
};
