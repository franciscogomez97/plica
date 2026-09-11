<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema «por puestos»: qué pasa con los empatados en una manga. Compartido
 * (los dos el 18) o promedio (18,5 cada uno, el sistema de federación).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->string('puestos_empate', 20)->default('compartido')->after('sistema_puntuacion');
        });
    }

    public function down(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->dropColumn('puestos_empate');
        });
    }
};
