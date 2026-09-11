<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Asistiré»: el socio dice que irá a una manga. Es una intención, no un
 * hecho: la asistencia real (la participación) la marca el admin al pasar
 * lista. Por eso vive en su propia tabla y nunca puntúa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('confirmacions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manga_id')->constrained()->cascadeOnDelete();
            $table->foreignId('socio_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['manga_id', 'socio_id']);
            $table->index('socio_id'); // SQLite no indexa FKs por sí solo
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('confirmacions');
    }
};
