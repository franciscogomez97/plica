<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Participación por participante: en una sección por equipos quien participa en
 * la manga es el equipo (la plica es suya), así que la participación apunta a
 * un socio O a un equipo. Las capturas siguen colgando de la participación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participacions', function (Blueprint $table) {
            $table->foreignId('socio_id')->nullable()->change();
            $table->foreignId('equipo_id')->nullable()->after('socio_id')->constrained('equipos')->restrictOnDelete();
            $table->unique(['manga_id', 'equipo_id']);
        });
    }

    public function down(): void
    {
        Schema::table('participacions', function (Blueprint $table) {
            $table->dropUnique(['manga_id', 'equipo_id']);
            $table->dropConstrainedForeignId('equipo_id');
        });
    }
};
