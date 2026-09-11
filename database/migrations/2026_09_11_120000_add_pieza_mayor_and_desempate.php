<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pieza mayor y desempate.
 *  - participacions.pieza_mayor_gramos: el pez más grande del pesaje (en
 *    secciones por medida se deduce de las capturas, que van pez a pez).
 *  - seccions.desempate: qué decide un empate ('piezas', 'peso' o
 *    'pieza_mayor'); si sigue igual, se comparte el puesto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participacions', function (Blueprint $table) {
            $table->unsignedInteger('pieza_mayor_gramos')->nullable()->after('plica');
        });

        Schema::table('seccions', function (Blueprint $table) {
            $table->string('desempate')->default('piezas')->after('descartes');
        });

        // Las secciones por piezas no pueden desempatar por piezas: por peso.
        DB::table('seccions')->where('criterio', 'piezas')->update(['desempate' => 'peso']);
    }

    public function down(): void
    {
        Schema::table('participacions', fn (Blueprint $table) => $table->dropColumn('pieza_mayor_gramos'));
        Schema::table('seccions', fn (Blueprint $table) => $table->dropColumn('desempate'));
    }
};
