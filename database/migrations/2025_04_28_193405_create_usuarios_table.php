<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Crear los tipos ENUM solo si no existen
        $enumExists = DB::select("SELECT 1 FROM pg_type WHERE typname = 'tipo_usuario_enum'");
        if (empty($enumExists)) {
            DB::statement("CREATE TYPE tipo_usuario_enum AS ENUM ('estudiante', 'administrativo', 'egresado', 'externo')");
        }

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('id_usuario');
            $table->string('email', 100)->unique();
            $table->string('password_hash');
            $table->enum('tipo_usuario', ['estudiante', 'administrativo', 'egresado', 'externo']);
            $table->foreignId('id_rol')->nullable()->constrained('roles', 'id_rol')->nullOnDelete();
            $table->uuid('ldap_uid')->unique()->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->nullable();
            $table->softDeletes('eliminado_en');
        });

        DB::statement("ALTER TABLE usuarios ALTER COLUMN tipo_usuario TYPE tipo_usuario_enum USING tipo_usuario::tipo_usuario_enum");
    }

    public function down()
    {
        Schema::dropIfExists('usuarios');
        DB::statement('DROP TYPE IF EXISTS tipo_usuario_enum');
    }
};
