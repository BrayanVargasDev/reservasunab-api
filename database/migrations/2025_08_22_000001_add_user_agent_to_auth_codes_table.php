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
        Schema::table('auth_codes', function (Blueprint $table) {
            // Guardar el agente de usuario del request; puede ser largo, por eso TEXT
            $table->text('user_agent')->nullable()->after('refresh_token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth_codes', function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });
    }
};
