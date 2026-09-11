<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema de la federación: qué puntos se lleva quien va a una manga y no
 * pesca (el «bolo»). Por defecto, la media de los puestos que quedan, que es
 * la fórmula oficial ((C + 1) + N) / 2 y lo que ya se hacía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->string('bolo', 20)->default('media')->after('puntos_no_asistencia');
            $table->unsignedSmallInteger('puntos_bolo')->default(0)->after('bolo');
        });
    }

    public function down(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->dropColumn(['bolo', 'puntos_bolo']);
        });
    }
};
