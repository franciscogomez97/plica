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
            $table->foreignId('socio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seccion_id')->nullable()->constrained('seccions')->nullOnDelete();
            $table->boolean('plica')->default(true);
            $table->timestamps();
            $table->unique(['manga_id', 'socio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participacions');
    }
};
