<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto del socio (ruta en el disco «public», WebP cuadrada) y número de
 * licencia federativa. La foto la pone el admin en la ficha o el socio en su
 * perfil; de momento solo se enseña en el listado de socios del admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('telefono');
            $table->string('licencia', 40)->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->dropColumn(['foto', 'licencia']);
        });
    }
};
