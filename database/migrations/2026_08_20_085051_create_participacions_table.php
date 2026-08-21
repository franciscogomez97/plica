<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participacions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manga_id')->constrained()->cascadeOnDelete();
            $table->foreignId('socio_id')->constrained()->restrictOnDelete();
            $table->foreignId('seccion_id')->nullable()->constrained('seccions')->nullOnDelete();
            $table->boolean('plica')->default(true);
            $table->timestamps();
            $table->unique(['manga_id', 'socio_id']);
            $table->index('socio_id'); // SQLite no indexa FKs por sí solo
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participacions');
    }
};
