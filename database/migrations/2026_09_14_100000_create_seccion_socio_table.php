<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Socios de cada sección. Se aprende sola (quien pesa en una manga de Orilla
 * pasa a ser de Orilla) y se corrige a mano en la ficha del socio o en la
 * sección. Con ella, el ranking lista también a los socios de la sección que
 * no han ido a ninguna manga (con sus puntos por ausencia), como hacen las
 * hojas de los clubes, y Socios y Mangas se filtran por sección.
 *
 * Al crearla se rellena con lo que ya hay: cada socio en las secciones donde
 * tiene algún pesaje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seccion_socio', function (Blueprint $table) {
            $table->foreignId('seccion_id')->constrained('seccions')->cascadeOnDelete();
            $table->foreignId('socio_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['seccion_id', 'socio_id']);
        });

        $ahora = now();
        $pares = DB::table('participacions')
            ->whereNotNull('seccion_id')
            ->select('seccion_id', 'socio_id')
            ->distinct()
            ->get()
            ->map(fn ($p) => ['seccion_id' => $p->seccion_id, 'socio_id' => $p->socio_id, 'created_at' => $ahora, 'updated_at' => $ahora])
            ->all();

        foreach (array_chunk($pares, 500) as $trozo) {
            DB::table('seccion_socio')->insert($trozo);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seccion_socio');
    }
};
