<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('socios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('nombre');
            $table->string('email')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invite_token', 64)->nullable()->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['club_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('socios');
    }
};
