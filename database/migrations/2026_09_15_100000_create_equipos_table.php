<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secciones por equipos (embarcación: dos por barco; carpfishing: equipos de
 * dos o más). La plica es del equipo.
 *  - seccions.modalidad: 'individual' (cada socio por su cuenta) o 'equipos'.
 *  - seccions.tamano_equipo: personas por equipo (2 por defecto).
 *  - equipos: un equipo de una sección en una temporada, con nombre opcional
 *    (sin nombre se enseñan los nombres de sus socios) y sus miembros.
 * Un club de orilla no nota nada: todo sigue siendo individual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->string('modalidad')->default('individual')->after('criterio');
            $table->unsignedSmallInteger('tamano_equipo')->default(2)->after('modalidad');
        });

        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('seccions')->restrictOnDelete();
            $table->foreignId('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('nombre')->nullable();
            $table->timestamps();
        });

        Schema::create('equipo_socio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->foreignId('socio_id')->constrained('socios')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['equipo_id', 'socio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_socio');
        Schema::dropIfExists('equipos');
        Schema::table('seccions', fn (Blueprint $table) => $table->dropColumn(['modalidad', 'tamano_equipo']));
    }
};
