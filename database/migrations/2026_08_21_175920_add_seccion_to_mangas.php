<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reglamento real del club piloto: cada sección tiene sus propias mangas.
     * Nullable a propósito: una manga sin sección es una jornada de todo el
     * club (varias secciones a la vez), como las que ya existen.
     */
    public function up(): void
    {
        Schema::table('mangas', function (Blueprint $table) {
            $table->foreignId('seccion_id')->nullable()->constrained('seccions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mangas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seccion_id');
        });
    }
};
