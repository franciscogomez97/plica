<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Scoring;
use Database\Seeders\BassMadridSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El primer club real: la hoja de Bass Madrid (Orilla 2026) cargada en Plica
 * tiene que dar el mismo ranking que su Excel. Los gramos de la hoja son
 * enteros por manga; sus totales llevan 1 o 2 g de más en seis socios
 * (decimales ocultos), así que aquí se comprueba contra la suma de las mangas.
 */
class BassMadridTest extends TestCase
{
    use RefreshDatabase;

    /** Puntos de la hoja (con la suma exacta de las mangas), en su orden. */
    private const RANKING = [
        'Fernando Arus' => 7290,
        'Juan Francisco Trujillo' => 6510,
        'Marius Florea' => 5100,
        'Daniel Gómez' => 5090,
        'Tomás Tomás' => 4680,
        'Tommy' => 4650,
        'José Durán' => 3920,
        'Tony' => 3790,
        'Paco Casado' => 3110,
        'David Garrido' => 2120,
        'Diego Magro' => 1950,
        'Enedino Villaverde' => 1920,
        'Domingo Antunez' => 1750,
        'Oliver Gomez' => 1500,
        'Valentin' => 1000,
        'Marius Modi' => 1000,
        'Juan Juarez' => 500,
        'Marcos Casado' => 500,
    ];

    public function test_el_ranking_de_orilla_es_el_de_la_hoja_del_club(): void
    {
        $this->seed(BassMadridSeeder::class);

        $club = Club::where('slug', 'bass-madrid')->firstOrFail();
        $temporada = Temporada::where('club_id', $club->id)->where('activa', true)->firstOrFail();

        $this->assertSame(23, Socio::where('club_id', $club->id)->count());
        $this->assertSame('Cada manga la gana quien más peso saca. El ranking suma el peso de todas las mangas. Cada manga a la que se va suma además 500 puntos de asistencia. Si empatan, gana quien más piezas saque; si siguen igual, comparten puesto.', Seccion::where('club_id', $club->id)->firstOrFail()->resumenReglas());

        $ranking = Scoring::rankingTemporada($temporada);
        $this->assertCount(1, $ranking);

        $orilla = $ranking->first();
        $this->assertSame('Orilla', $orilla->nombre);
        $this->assertSame(5, $orilla->numMangas);
        // Mismos puntos para todos (los empatados pueden salir en cualquier orden entre sí).
        $puntos = $orilla->filas->mapWithKeys(fn (object $f) => [$f->socio->nombre => $f->puntos])->all();
        $esperado = self::RANKING;
        ksort($puntos);
        ksort($esperado);
        $this->assertSame($esperado, $puntos);
        $this->assertSame(array_values(self::RANKING), $orilla->filas->pluck('puntos')->all());

        // Puestos: Valentin y Marius Modi comparten el 15º; Juan Juarez y Marcos Casado, el 17º.
        $puestos = $orilla->filas->mapWithKeys(fn (object $f) => [$f->socio->nombre => $f->puesto]);
        $this->assertSame(1, $puestos['Fernando Arus']);
        $this->assertSame(15, $puestos['Valentin']);
        $this->assertSame(15, $puestos['Marius Modi']);
        $this->assertSame(17, $puestos['Juan Juarez']);
        $this->assertSame(17, $puestos['Marcos Casado']);

        // Pieza mayor de la temporada: la de 2040 g de Juan Francisco en Sierra Brava (2ª manga).
        $this->assertSame('Juan Francisco Trujillo', $orilla->piezaMayor->socio->nombre);
        $this->assertSame(2040, $orilla->piezaMayor->valor);
        $this->assertSame('2ª Manga', $orilla->piezaMayor->manga->nombre);

        // Totales de la hoja que sí cuadran al gramo.
        $filas = $orilla->filas->keyBy(fn (object $f) => $f->socio->nombre);
        $this->assertSame(7, $filas['Fernando Arus']->piezas);
        $this->assertSame(5, $filas['Fernando Arus']->mangas);
        $this->assertSame(3090, $filas['Daniel Gómez']->peso);
        $this->assertSame(2110, $filas['Paco Casado']->peso);
        $this->assertSame(1500, $filas['Oliver Gomez']->puntos); // tres asistencias sin pescar
    }

    public function test_cada_manga_la_gana_quien_dice_la_hoja_y_su_pieza_mayor(): void
    {
        $this->seed(BassMadridSeeder::class);

        $club = Club::where('slug', 'bass-madrid')->firstOrFail();
        $mangas = Manga::whereHas('temporada', fn ($q) => $q->where('club_id', $club->id))->orderBy('fecha')->get();

        $this->assertCount(7, $mangas);
        $this->assertSame(['Almaraz', 'Sierra Brava', 'Navalcán', 'Cíjara', 'García de Sola', 'Navalcán', 'Sierra Brava'], $mangas->pluck('lugar')->all());

        $ganadores = $mangas->take(5)->map(function (Manga $manga) {
            $grupo = Scoring::clasificacionManga($manga)->first();

            return [
                $grupo->filas->where('puesto', 1)->pluck('socio.nombre')->sort()->values()->all(),
                $grupo->piezaMayor?->socio->nombre,
                $grupo->piezaMayor?->valor,
            ];
        })->all();

        $this->assertSame([
            [['Fernando Arus'], 'Fernando Arus', 1050],
            [['Juan Francisco Trujillo'], 'Juan Francisco Trujillo', 2040],
            [['Daniel Gómez'], 'Daniel Gómez', 1780],
            [['Fernando Arus'], 'José Durán', 1380], // gana Fernando con 2 piezas; la mayor es la de José
            // En García de Sola nadie pescó: los cuatro que fueron comparten el 1º.
            [['Fernando Arus', 'Juan Francisco Trujillo', 'Marius Florea', 'Tony'], null, null],
        ], $ganadores);

        // La penalización de Tomás en Navalcán queda explicada en la captura.
        $tomas = Socio::where('nombre', 'Tomás Tomás')->firstOrFail();
        $captura = $mangas[2]->participacions()->where('socio_id', $tomas->id)->firstOrFail()->capturas()->firstOrFail();
        $this->assertSame(820, $captura->peso_gramos);
        $this->assertStringContainsString('penalización de 1480 g', $captura->nota);

        // Las dos de septiembre están sin pesar: la del día 6 ya toca gestionarla.
        $this->assertSame(Manga::ESTADO_PROGRAMADA, $mangas[5]->estado);
        $this->assertTrue($mangas[5]->pendienteDeGestion());
        $this->assertSame(Manga::ESTADO_PROGRAMADA, $mangas[6]->estado);
    }

    public function test_volver_a_ejecutarlo_no_duplica_ni_pisa_nada(): void
    {
        $this->seed(BassMadridSeeder::class);

        $club = Club::where('slug', 'bass-madrid')->firstOrFail();
        $fernando = Socio::where('club_id', $club->id)->where('nombre', 'Fernando Arus')->firstOrFail();

        // El admin corrige un peso a mano; el seeder no debe deshacerlo.
        $captura = $fernando->participacions()->firstOrFail()->capturas()->firstOrFail();
        $captura->update(['peso_gramos' => 2442]);

        $this->seed(BassMadridSeeder::class);

        $this->assertSame(1, Club::where('slug', 'bass-madrid')->count());
        $this->assertSame(23, Socio::where('club_id', $club->id)->count());
        $this->assertSame(7, Manga::whereHas('temporada', fn ($q) => $q->where('club_id', $club->id))->count());
        $this->assertSame(2442, $captura->fresh()->peso_gramos);
    }

    public function test_el_club_plica_vacio_del_despliegue_pasa_a_ser_bass_madrid_con_su_admin(): void
    {
        // Como en producción: plica:club creó un club «Plica» vacío con su temporada y su admin.
        $this->artisan('plica:club', ['nombre' => 'Plica', 'admin_email' => 'fran@plica.test'])->assertSuccessful();
        $plica = Club::where('slug', 'plica')->firstOrFail();

        $this->seed(BassMadridSeeder::class);

        $this->assertDatabaseMissing('clubs', ['slug' => 'plica']);
        $club = Club::where('slug', 'bass-madrid')->firstOrFail();
        $this->assertSame($plica->id, $club->id);
        $this->assertSame('Bass Madrid', $club->nombre);
        $this->assertSame(23, $club->socios()->count());
        $this->assertSame(1, $club->temporadas()->count()); // la «Temporada 2026» de plica:club, no otra
        $this->assertSame($club->id, User::where('email', 'fran@plica.test')->firstOrFail()->club_id);
    }

    public function test_el_admin_ve_a_los_23_socios_de_una_vez_sin_paginar(): void
    {
        $this->seed(BassMadridSeeder::class);
        $club = Club::where('slug', 'bass-madrid')->firstOrFail();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@bassmadrid.test', 'password' => 'secreto123', 'club_id' => $club->id, 'role' => User::ROLE_ADMIN]);

        $this->actingAs($admin);
        $respuesta = $this->get('/admin/socios')->assertOk();
        foreach (BassMadridSeeder::SOCIOS as $nombre) {
            $respuesta->assertSee($nombre);
        }
    }

    public function test_el_ranking_publico_de_orilla_se_puede_compartir(): void
    {
        $this->seed(BassMadridSeeder::class);

        $this->get('/c/bass-madrid/orilla')->assertOk()
            ->assertSee('Bass Madrid')
            ->assertSee('Fernando Arus')
            ->assertSee('Juan Francisco Trujillo')
            ->assertSee('2,040 kg');
    }
}
