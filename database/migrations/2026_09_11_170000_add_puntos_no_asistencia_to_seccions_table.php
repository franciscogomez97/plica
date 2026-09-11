<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Algunos clubes dan puntos también por las mangas a las que no se va (o los
 * quitan: puede ser negativo). Por defecto 0: nada cambia para nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->integer('puntos_no_asistencia')->default(0)->after('puntos_participacion');
        });
    }

    public function down(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->dropColumn('puntos_no_asistencia');
        });
    }
};
