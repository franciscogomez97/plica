<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horario de la manga (a qué hora empieza y termina) y quedada previa: dónde
 * y a qué hora se junta el club antes de ir al embalse (un bar, una
 * gasolinera), con su enlace de mapa. Todo opcional: sale en la convocatoria
 * y en la página de la manga cuando está puesto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mangas', function (Blueprint $table) {
            $table->time('hora_inicio')->nullable()->after('fecha');
            $table->time('hora_fin')->nullable()->after('hora_inicio');
            $table->string('quedada_lugar', 160)->nullable()->after('ubicacion_url');
            $table->time('quedada_hora')->nullable()->after('quedada_lugar');
            $table->string('quedada_url', 500)->nullable()->after('quedada_hora');
        });
    }

    public function down(): void
    {
        Schema::table('mangas', function (Blueprint $table) {
            $table->dropColumn(['hora_inicio', 'hora_fin', 'quedada_lugar', 'quedada_hora', 'quedada_url']);
        });
    }
};
