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
        Schema::table('reservas', function (Blueprint $table) {
            $table->decimal('precio_base', 10, 2)->default(0);
            $table->decimal('precio_espacio', 10, 2)->default(0);
            $table->decimal('precio_elementos', 10, 2)->default(0);
            $table->decimal('precio_total', 10, 2)->default(0);
            $table->decimal('porcentaje_aplicado', 3, 1)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('precio_base');
            $table->dropColumn('precio_espacio');
            $table->dropColumn('precio_elementos');
            $table->dropColumn('precio_total');
            $table->dropColumn('porcentaje_aplicado');
        });
    }
};
