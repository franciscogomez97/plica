<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número de socios de la sección, a título informativo: no entra en ningún
 * cálculo. Lo pidió Bass Extremadura para tenerlo a la vista (y para poner
 * los puntos por ausencia: socios + 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->unsignedSmallInteger('numero_socios')->nullable()->after('criterio');
        });
    }

    public function down(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->dropColumn('numero_socios');
        });
    }
};
