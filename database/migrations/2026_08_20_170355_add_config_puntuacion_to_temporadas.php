<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporadas', function (Blueprint $table) {
            $table->string('sistema_puntuacion')->default('acumulado'); // acumulado | puestos
            $table->unsignedInteger('puntos_participacion')->default(0);
            $table->unsignedSmallInteger('descartes')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('temporadas', function (Blueprint $table) {
            $table->dropColumn(['sistema_puntuacion', 'puntos_participacion', 'descartes']);
        });
    }
};
