<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Toda manga es de una sección (decisión de septiembre de 2026: el club
 * siempre compite por secciones). Las «jornadas de todo el club» que hubiera
 * se reparten: la manga se queda con la primera sección de sus participaciones
 * y se crea una copia por cada otra sección, moviéndole sus participaciones.
 * Una sección con mangas no se puede borrar (restricción en base de datos).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('mangas')->whereNull('seccion_id')->orderBy('id')->get() as $manga) {
            $secciones = DB::table('participacions')
                ->where('manga_id', $manga->id)
                ->whereNotNull('seccion_id')
                ->distinct()
                ->orderBy('seccion_id')
                ->pluck('seccion_id');

            $principal = $secciones->first() ?? $this->seccionPorDefecto($manga->temporada_id);

            DB::table('mangas')->where('id', $manga->id)->update(['seccion_id' => $principal]);
            DB::table('participacions')->where('manga_id', $manga->id)->whereNull('seccion_id')->update(['seccion_id' => $principal]);

            foreach ($secciones->slice(1) as $otra) {
                $copiaId = DB::table('mangas')->insertGetId([
                    'temporada_id' => $manga->temporada_id,
                    'seccion_id' => $otra,
                    'nombre' => $manga->nombre,
                    'fecha' => $manga->fecha,
                    'lugar' => $manga->lugar,
                    'ubicacion_url' => $manga->ubicacion_url,
                    'estado' => $manga->estado,
                    'notas' => $manga->notas,
                    'created_at' => $manga->created_at,
                    'updated_at' => now(),
                ]);

                DB::table('participacions')
                    ->where('manga_id', $manga->id)
                    ->where('seccion_id', $otra)
                    ->update(['manga_id' => $copiaId]);
            }
        }

        Schema::table('mangas', function (Blueprint $table) {
            $table->dropForeign(['seccion_id']);
        });

        Schema::table('mangas', function (Blueprint $table) {
            $table->unsignedBigInteger('seccion_id')->nullable(false)->change();
        });

        Schema::table('mangas', function (Blueprint $table) {
            $table->foreign('seccion_id')->references('id')->on('seccions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mangas', function (Blueprint $table) {
            $table->dropForeign(['seccion_id']);
        });

        Schema::table('mangas', function (Blueprint $table) {
            $table->unsignedBigInteger('seccion_id')->nullable()->change();
        });

        Schema::table('mangas', function (Blueprint $table) {
            $table->foreign('seccion_id')->references('id')->on('seccions')->nullOnDelete();
        });
    }

    /** Una manga sin participaciones ni sección: la primera sección del club (se crea una si no hay). */
    private function seccionPorDefecto(int $temporadaId): int
    {
        $clubId = DB::table('temporadas')->where('id', $temporadaId)->value('club_id');
        $seccionId = DB::table('seccions')->where('club_id', $clubId)->orderBy('id')->value('id');

        return $seccionId ?? DB::table('seccions')->insertGetId([
            'club_id' => $clubId,
            'nombre' => 'General',
            'slug' => 'general',
            'criterio' => 'peso',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
