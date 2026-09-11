<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Enlace a Google Maps (u otro) del punto de encuentro, para la convocatoria. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mangas', function (Blueprint $table) {
            $table->string('ubicacion_url', 500)->nullable()->after('lugar');
        });
    }

    public function down(): void
    {
        Schema::table('mangas', fn (Blueprint $table) => $table->dropColumn('ubicacion_url'));
    }
};
