<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participacion_id')->constrained('participacions')->cascadeOnDelete();
            $table->unsignedInteger('piezas')->default(1);
            $table->unsignedInteger('peso_gramos')->default(0);
            $table->string('nota')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capturas');
    }
};
