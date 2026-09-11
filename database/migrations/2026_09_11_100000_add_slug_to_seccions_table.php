<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Slug por sección para los enlaces públicos que se comparten por WhatsApp
 * (/c/club/orilla). Único dentro del club; se genera una vez y no cambia
 * aunque la sección se renombre, para que los enlaces compartidos no mueran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('nombre');
            $table->unique(['club_id', 'slug']);
        });

        $usados = [];

        foreach (DB::table('seccions')->orderBy('id')->get() as $seccion) {
            $base = Str::slug($seccion->nombre) ?: 'seccion';
            $slug = $base;

            for ($i = 2; isset($usados[$seccion->club_id][$slug]); $i++) {
                $slug = "{$base}-{$i}";
            }

            $usados[$seccion->club_id][$slug] = true;
            DB::table('seccions')->where('id', $seccion->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('seccions', function (Blueprint $table) {
            $table->dropUnique(['club_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
