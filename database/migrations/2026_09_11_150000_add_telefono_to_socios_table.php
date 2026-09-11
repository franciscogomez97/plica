<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teléfono del socio: con él, «Dar acceso» abre directamente su chat de
 * WhatsApp con el enlace escrito, sin buscar el contacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->string('telefono', 40)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->dropColumn('telefono');
        });
    }
};
