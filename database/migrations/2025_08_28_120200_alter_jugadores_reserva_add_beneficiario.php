<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('jugadores_reserva', 'id_beneficiario')) {
            Schema::table('jugadores_reserva', function (Blueprint $table) {
                $table->unsignedBigInteger('id_beneficiario')->nullable()->after('id_usuario');
            });

            DB::statement('ALTER TABLE jugadores_reserva ADD CONSTRAINT jugadores_reserva_id_beneficiario_foreign FOREIGN KEY (id_beneficiario) REFERENCES beneficiarios(id) ON DELETE CASCADE');
        }

        try {
            DB::statement('ALTER TABLE jugadores_reserva ALTER COLUMN id_usuario DROP NOT NULL');
        } catch (\Throwable $e) {
            // ignorar si ya es nullable
        }

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS jugadores_reserva_reserva_usuario_idx ON jugadores_reserva(id_reserva, id_usuario)');
        } catch (\Throwable $e) {
        }
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS jugadores_reserva_reserva_benef_idx ON jugadores_reserva(id_reserva, id_beneficiario)');
        } catch (\Throwable $e) {
        }

        foreach (['unique_jugador_reserva', 'jugadores_reserva_id_reserva_id_usuario_unique'] as $oldUnique) {
            try {
                DB::statement("ALTER TABLE jugadores_reserva DROP CONSTRAINT IF EXISTS {$oldUnique}");
            } catch (\Throwable $e) {
            }
        }

        try {
            DB::statement('ALTER TABLE jugadores_reserva ADD CONSTRAINT unique_jugador_reserva_usuario UNIQUE (id_reserva, id_usuario)');
        } catch (\Throwable $e) {
        }
        try {
            DB::statement('ALTER TABLE jugadores_reserva ADD CONSTRAINT unique_jugador_reserva_beneficiario UNIQUE (id_reserva, id_beneficiario)');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        foreach (['unique_jugador_reserva_usuario', 'unique_jugador_reserva_beneficiario'] as $uq) {
            try {
                DB::statement("ALTER TABLE jugadores_reserva DROP CONSTRAINT IF EXISTS {$uq}");
            } catch (\Throwable $e) {
            }
        }

        foreach (['jugadores_reserva_reserva_usuario_idx', 'jugadores_reserva_reserva_benef_idx'] as $idx) {
            try {
                DB::statement("DROP INDEX IF EXISTS {$idx}");
            } catch (\Throwable $e) {
            }
        }

        try {
            DB::statement('ALTER TABLE jugadores_reserva DROP CONSTRAINT IF EXISTS jugadores_reserva_id_beneficiario_foreign');
        } catch (\Throwable $e) {
        }
        if (Schema::hasColumn('jugadores_reserva', 'id_beneficiario')) {
            Schema::table('jugadores_reserva', function (Blueprint $table) {
                $table->dropColumn('id_beneficiario');
            });
        }

        try {
            DB::statement('ALTER TABLE jugadores_reserva ALTER COLUMN id_usuario SET NOT NULL');
        } catch (\Throwable $e) {
        }

        try {
            DB::statement('ALTER TABLE jugadores_reserva ADD CONSTRAINT unique_jugador_reserva UNIQUE (id_reserva, id_usuario)');
        } catch (\Throwable $e) {
        }
    }
};
