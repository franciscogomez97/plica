<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Manga;
use App\Services\Scoring;
use Database\Seeders\BassExtremaduraMangasSeeder;
use Database\Seeders\BassExtremaduraSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bass Extremadura, Orilla 2026, con sus dos mangas reales cargadas: el
 * ranking de Plica tiene que dar la columna «Puntuacion final» de su
 * «General Provisional Orilla 2026» (hoja del 25 de junio de 2026), socio a
 * socio, y cada manga sus puntos (I Manga Orellana 15/3/26 y II Manga).
 */
class BassExtremaduraTemporadaTest extends TestCase
{
    use RefreshDatabase;

    /** «Puntuacion final» de su hoja, los 47: también los cuatro que no han ido a ninguna (48 + 48 = 96). */
    private const GENERAL = [
        'Angel Vazquez' => 6, 'Fernando Díaz' => 9, 'Miguel García' => 13, 'Rafael Tomé' => 16, 'Adrián Fernandez' => 17,
        'Victor Moreno' => 29.5, 'Cristofer Pérez' => 30, 'Eduardo Vega' => 30.5, 'Fco. Javier Moreno' => 30.5, 'Alvaro Tarifa' => 31.5,
        'Francisco Dorado' => 32.5, 'Manuel Martín' => 34.5, 'Ismael Arroyo' => 35.5, 'Diego Ruiz' => 36.5, 'David Machuca' => 38.5,
        'David Granado' => 39.5, 'Felix Daniel Rodriguez' => 40.5, 'Alberto Enrique' => 41.5, 'Antonio Calvo' => 43.5, 'Oscar Hidalgo' => 45,
        'Juan Pedro Jerez' => 47.5, 'Sergio Guerrero' => 51,
        'Eduardo Soria' => 52, 'Javier Soria' => 52, 'Jose María Lopez' => 52, 'Josué Mateos' => 52, 'Oscar Ramos' => 52, 'Pablo Liberal' => 52,
        'Ismael Sierra' => 55, 'Jorge Kopa' => 55, 'Jorge Chorro' => 57, 'Pedro Yuste' => 66.5,
        'Agustín Gomez Maya' => 74.5, 'Álvaro González' => 74.5, 'Blanca García' => 74.5, 'Carlos Mateos' => 74.5, 'David Gomez' => 74.5,
        'Javier Casas' => 74.5, 'Jose Benitez' => 74.5, 'Lukas Cuadrado' => 74.5, 'Miguel A. de Marcos' => 74.5, 'Miguel Marín' => 74.5,
        'Rufino Gomez Maya' => 74.5,
        'David Basart' => 96, 'David Garrido' => 96, 'Diego Magro' => 96, 'Pablo Mediano' => 96,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BassExtremaduraSeeder::class);
        $this->seed(BassExtremaduraMangasSeeder::class);
    }

    public function test_la_general_provisional_es_la_de_su_hoja(): void
    {
        $club = Club::where('slug', 'bass-extremadura')->firstOrFail();
        $grupo = Scoring::rankingTemporada($club->temporadaActiva())->firstWhere('nombre', 'Orilla');

        $puntos = $grupo->filas->mapWithKeys(fn (object $f) => [$f->socio->nombre => $f->puntos])->all();
        $this->assertCount(47, $puntos); // los 47 socios de Orilla, hayan ido o no
        foreach (self::GENERAL as $nombre => $esperado) {
            $this->assertEquals($esperado, $puntos[$nombre] ?? null, $nombre);
        }

        // Puestos: Ángel 1º, Fernando 2º; los seis empatados a 52 comparten puesto.
        $puestos = $grupo->filas->mapWithKeys(fn (object $f) => [$f->socio->nombre => $f->puesto]);
        $this->assertSame(1, $puestos['Angel Vazquez']);
        $this->assertSame(2, $puestos['Fernando Díaz']);
        $this->assertSame($puestos['Eduardo Soria'], $puestos['Pablo Liberal']);
        $this->assertSame(44, $puestos['David Basart']); // los cuatro de 96, últimos y empatados
        $this->assertSame(0, $grupo->filas->first(fn (object $f) => $f->socio->nombre === 'David Basart')->mangas);

        // Pieza mayor de la temporada: los 2.975 g de Adrián en Orellana.
        $this->assertSame('Adrián Fernandez', $grupo->piezaMayor->socio->nombre);
        $this->assertSame(2975, $grupo->piezaMayor->valor);
    }

    public function test_pato_y_embarcacion_tienen_las_normas_de_orilla_y_estan_vacias(): void
    {
        $club = Club::where('slug', 'bass-extremadura')->firstOrFail();
        $this->assertSame(['Embarcación', 'Orilla', 'Pato'], $club->seccions()->orderBy('nombre')->pluck('nombre')->all());

        $orilla = $club->seccions()->where('slug', 'orilla')->firstOrFail();
        $normas = ['criterio', 'sistema_puntuacion', 'puntos_participacion', 'puntos_no_asistencia', 'bolo', 'puntos_bolo', 'descartes', 'descartes_ausencias', 'desempate'];

        foreach (['pato', 'embarcacion'] as $slug) {
            $seccion = $club->seccions()->where('slug', $slug)->firstOrFail();
            $this->assertSame($orilla->only($normas), $seccion->only($normas), $slug);
            $this->assertNull($seccion->numero_socios); // no se sabe cuántos son: no se inventa
            $this->assertSame(0, $seccion->socios()->count());
            $this->assertSame(0, $seccion->mangas()->count());
        }

        // Y el ranking de la temporada no se cae con secciones sin mangas.
        $this->assertNotNull(Scoring::rankingTemporada($club->temporadaActiva())->firstWhere('nombre', 'Orilla'));
    }

    public function test_cada_manga_puntua_como_su_hoja(): void
    {
        $club = Club::where('slug', 'bass-extremadura')->firstOrFail();
        [$primera, $segunda] = Manga::whereHas('temporada', fn ($q) => $q->where('club_id', $club->id))->orderBy('fecha')->get();

        // I Manga: 11 pescaron, 41 fueron: los 30 bolos se llevan (12 + 41) / 2 = 26,5; Adrián 1, Rafael 11.
        $puntos = Scoring::puntosPorPuesto(Scoring::clasificacionManga($primera)->first()->filas, 'promedio', 'media');
        $this->assertCount(41, $puntos);
        $this->assertCount(30, array_filter($puntos, fn ($p) => $p == 26.5));
        $porNombre = collect(Scoring::clasificacionManga($primera)->first()->filas)->mapWithKeys(fn ($f) => [$f->socio->nombre => $puntos[$f->socio->id]]);
        $this->assertSame(1, $porNombre['Adrián Fernandez']);
        $this->assertSame(11, $porNombre['Rafael Tomé']);

        // II Manga: 21 pescaron, 29 fueron: los 8 bolos, 25,5; Óscar y Pedro empatan a 435 y se reparten 18,5.
        $puntos = Scoring::puntosPorPuesto(Scoring::clasificacionManga($segunda)->first()->filas, 'promedio', 'media');
        $this->assertCount(29, $puntos);
        $this->assertCount(8, array_filter($puntos, fn ($p) => $p == 25.5));
        $this->assertCount(2, array_filter($puntos, fn ($p) => $p == 18.5));

        // Solo esas dos mangas: en su general las columnas III, IV y V están vacías.
        $this->assertSame(2, Manga::whereHas('temporada', fn ($q) => $q->where('club_id', $club->id))->count());

        // Volver a ejecutarlo no duplica.
        $this->seed(BassExtremaduraMangasSeeder::class);
        $this->assertSame(2, Manga::whereHas('temporada', fn ($q) => $q->where('club_id', $club->id))->count());
    }
}
