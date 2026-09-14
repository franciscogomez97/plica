<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema de la federación: el empate en la general no es el de la manga. En
 * la manga se reparten puntos (promedio) o comparten puesto; en el ranking
 * del año los reglamentos desempatan por otra cosa (más gramos en el año o
 * mejor manga en Castilla-La Mancha; pieza mayor o menos capturas en la
 * FEPyC). Por defecto, «comparten», que es lo que se hacía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->string('desempate_general', 20)->default('compartido')->after('desempate');
        });
    }

    public function down(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->dropColumn('desempate_general');
        });
    }
};
