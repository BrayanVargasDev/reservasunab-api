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
        Schema::table('espacios', function (Blueprint $table) {
            if (!Schema::hasColumn('espacios', 'pago_mensual')) {
                $table->boolean('pago_mensual')->default(false);
            }
            if (!Schema::hasColumn('espacios', 'valor_mensualidad')) {
                $table->decimal('valor_mensualidad', 12, 2)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('espacios', function (Blueprint $table) {
            if (Schema::hasColumn('espacios', 'valor_mensualidad')) {
                $table->dropColumn('valor_mensualidad');
            }
            if (Schema::hasColumn('espacios', 'pago_mensual')) {
                $table->dropColumn('pago_mensual');
            }
        });
    }
};
