<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qué mangas se pueden descartar: solo las pescadas (se quita la peor de las
 * que fue) o también las no pescadas (faltar es la peor manga y se descarta
 * la primera). Hasta ahora «suma lo pescado» hacía lo primero y «por puestos»
 * lo segundo sin decirlo: se deja cada sección como venía funcionando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->boolean('descartes_ausencias')->default(false)->after('descartes');
        });

        DB::table('seccions')->where('sistema_puntuacion', 'puestos')->update(['descartes_ausencias' => true]);
    }

    public function down(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->dropColumn('descartes_ausencias');
        });
    }
};
