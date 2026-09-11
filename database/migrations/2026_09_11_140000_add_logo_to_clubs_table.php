<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Logotipo del club: ruta en el disco «public» (siempre WebP, ver LogoClub). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('perfil_publico');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', fn (Blueprint $table) => $table->dropColumn('logo'));
    }
};
