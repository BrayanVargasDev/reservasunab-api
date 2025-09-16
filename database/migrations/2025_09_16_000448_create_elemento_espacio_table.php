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
        Schema::create('elementos_espacios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_espacio')->constrained('espacios')->onDelete('cascade');
            $table->foreignId('id_elemento')->constrained('elementos')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['id_espacio', 'id_elemento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('elementos_espacios');
    }
};
