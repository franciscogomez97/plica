<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una sola regla de empates por sección: «desempate» pasa a admitir también
 * «compartido» (no se desempata, comparten puesto) y «promedio» (por puestos:
 * se reparten el promedio). Antes había dos ajustes que se pisaban (el
 * desempate por pieza mayor se aplicaba antes que el promedio).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('seccions')
            ->where('sistema_puntuacion', 'puestos')
            ->where('puestos_empate', 'promedio')
            ->update(['desempate' => 'promedio']);

        Schema::table('seccions', function (Blueprint $table) {
            $table->dropColumn('puestos_empate');
        });
    }

    public function down(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->string('puestos_empate', 20)->default('compartido')->after('sistema_puntuacion');
        });

        DB::table('seccions')->where('desempate', 'promedio')->update(['puestos_empate' => 'promedio', 'desempate' => 'pieza_mayor']);
        DB::table('seccions')->where('desempate', 'compartido')->update(['desempate' => 'pieza_mayor']);
    }
};
