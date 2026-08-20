<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->string('criterio')->default('peso'); // peso | medida | piezas
        });

        Schema::table('capturas', function (Blueprint $table) {
            $table->unsignedInteger('medida_mm')->nullable()->after('peso_gramos');
        });
    }

    public function down(): void
    {
        Schema::table('seccions', fn (Blueprint $table) => $table->dropColumn('criterio'));
        Schema::table('capturas', fn (Blueprint $table) => $table->dropColumn('medida_mm'));
    }
};
