<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mangas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temporada_id')->constrained()->cascadeOnDelete();
            $table->string('nombre');
            $table->date('fecha');
            $table->string('lugar')->nullable();
            $table->string('estado')->default('programada'); // programada | celebrada
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->index(['temporada_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mangas');
    }
};
