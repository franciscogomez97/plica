<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\User;
use App\Services\Compartir;
use App\Services\Podio;
use App\Services\Scoring;
use App\Support\Participante;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paso 2 de las secciones por equipos: la participación en la manga es del
 * equipo y el motor puntúa por participante, sea socio o equipo, sin repartir
 * nada. Un club de orilla no nota nada (toda la suite anterior sigue igual).
 */
class PuntuacionPorEquiposTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Seccion $embarcacion;

    private Equipo $lucios;

    private Equipo $barcoDos;

    private Equipo $sinManga;

    private Manga $manga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $this->embarcacion = Seccion::where('nombre', 'Embarcación')->firstOrFail();
        $this->embarcacion->update(['modalidad' => Seccion::MODALIDAD_EQUIPOS, 'tamano_equipo' => 2, 'puntos_participacion' => 0, 'descartes' => 0]);
        $temporada = $this->club->temporadaActiva();

        $this->club->altaDeEquipos("Los Lucios: Mario López / Sergio del Río\nAlberto Rey / Toni Salgado\nPaco Jiménez / Iván Perea", $this->embarcacion, $temporada);
        $this->lucios = Equipo::where('nombre', 'Los Lucios')->firstOrFail();
        $this->barcoDos = Equipo::whereNull('nombre')->whereHas('socios', fn ($q) => $q->where('nombre', 'Alberto Rey'))->firstOrFail();
        $this->sinManga = Equipo::whereNull('nombre')->whereHas('socios', fn ($q) => $q->where('nombre', 'Paco Jiménez'))->firstOrFail();

        // Una manga de embarcación: pescan dos barcos; el tercero no fue.
        $this->manga = Manga::create(['temporada_id' => $temporada->id, 'seccion_id' => $this->embarcacion->id, 'nombre' => 'Manga de barcos', 'fecha' => today()->subDays(3), 'estado' => Manga::ESTADO_CELEBRADA]);
        $p1 = $this->manga->participacions()->create(['equipo_id' => $this->lucios->id, 'seccion_id' => $this->embarcacion->id, 'pieza_mayor_gramos' => 2100]);
        $p1->capturas()->create(['piezas' => 3, 'peso_gramos' => 6400]);
        $p2 = $this->manga->participacions()->create(['equipo_id' => $this->barcoDos->id, 'seccion_id' => $this->embarcacion->id]);
        $p2->capturas()->create(['piezas' => 2, 'peso_gramos' => 4200]);
    }

    public function test_la_participacion_es_del_equipo_y_sus_socios_quedan_en_la_seccion(): void
    {
        $p = $this->manga->participacions()->firstOrFail();
        $this->assertNull($p->socio_id);
        $this->assertTrue($p->participante()->esEquipo);
        $this->assertSame($this->lucios->id, $p->participanteId());

        // Los dos socios del barco son ya socios de Embarcación, y «· tú» sabe que están en él.
        foreach (['Mario López', 'Sergio del Río'] as $nombre) {
            $socio = Socio::where('nombre', $nombre)->firstOrFail();
            $this->assertTrue($socio->seccions->contains($this->embarcacion));
            $this->assertTrue($p->participante()->incluye($socio->id));
        }
        $this->assertFalse($p->participante()->incluye(Socio::where('nombre', 'Alberto Rey')->firstOrFail()->id));
    }

    public function test_la_clasificacion_de_la_manga_es_de_equipos_con_la_plica_entera(): void
    {
        $grupo = Scoring::clasificacionManga($this->manga)->first();

        $this->assertSame(['Los Lucios', 'Alberto Rey / Toni Salgado'], $grupo->filas->map(fn ($f) => $f->participante->nombre)->all());
        $this->assertSame([1, 2], $grupo->filas->pluck('puesto')->all());
        $this->assertSame(6400, $grupo->filas->first()->peso); // nada de repartir 3,2 kg a cada uno
        $this->assertSame('6,400 kg', Scoring::valorPrincipal($grupo->criterio, $grupo->filas->first()));
        $this->assertSame('Los Lucios', $grupo->piezaMayor->participante->nombre);
        $this->assertSame('2,100 kg', $grupo->piezaMayor->texto);

        // El alias ->socio sigue valiendo para quien lo lea (vistas y textos).
        $this->assertSame('Los Lucios', $grupo->filas->first()->socio->nombre);
        $this->assertStringContainsString('1º Los Lucios · 6,400 kg', Compartir::textoManga($this->manga, Scoring::clasificacionManga($this->manga)));
    }

    public function test_el_ranking_y_el_cuadro_van_por_equipos_e_incluyen_al_que_no_fue(): void
    {
        $temporada = $this->club->temporadaActiva();
        $grupo = Scoring::rankingTemporada($temporada)->firstWhere('seccionId', $this->embarcacion->id);

        $this->assertSame(
            ['Los Lucios', 'Alberto Rey / Toni Salgado', 'Iván Perea / Paco Jiménez'],
            $grupo->filas->map(fn ($f) => $f->participante->nombre)->all(),
        );
        $this->assertSame([6400, 4200, 0], $grupo->filas->pluck('puntos')->all());
        $this->assertSame([1, 1, 0], $grupo->filas->pluck('mangas')->all());

        $cuadro = Scoring::cuadroSeccion($temporada, $this->embarcacion);
        $this->assertCount(3, $cuadro->filas);
        $lucios = $cuadro->filas->first();
        $this->assertSame('Los Lucios', $lucios->participante->nombre);
        $this->assertSame(1, $lucios->celdas[$this->manga->id]->puesto);
        $this->assertTrue($lucios->celdas[$this->manga->id]->mayorDeLaManga);
        $this->assertNull($cuadro->filas->last()->celdas[$this->manga->id]);
    }

    public function test_por_puestos_los_puntos_van_por_equipo(): void
    {
        $this->embarcacion->update(['sistema_puntuacion' => Seccion::SISTEMA_PUESTOS]);
        $temporada = $this->club->temporadaActiva();

        $grupo = Scoring::rankingTemporada($temporada)->firstWhere('seccionId', $this->embarcacion->id);
        // 1 y 2 puntos para los que fueron; el que no fue, el último más uno (3).
        $this->assertSame(['Los Lucios' => 1, 'Alberto Rey / Toni Salgado' => 2, 'Iván Perea / Paco Jiménez' => 3],
            $grupo->filas->mapWithKeys(fn ($f) => [$f->participante->nombre => $f->puntos])->all());

        $puntos = Scoring::puntosPorPuesto(Scoring::clasificacionManga($this->manga)->first()->filas, Seccion::DESEMPATE_COMPARTIDO);
        $this->assertSame([$this->lucios->id => 1, $this->barcoDos->id => 2], $puntos);
    }

    public function test_las_paginas_y_la_tarjeta_pintan_los_equipos(): void
    {
        $this->get('/c/cd-pesca-piloto/manga/'.$this->manga->id)->assertOk()
            ->assertSee('Los Lucios')
            ->assertSee('Alberto Rey / Toni Salgado')
            ->assertSee('6,400 kg');
        $this->get('/c/cd-pesca-piloto/embarcacion')->assertOk()
            ->assertSee('Los Lucios')
            ->assertSee('Iván Perea / Paco Jiménez')
            ->assertSee('Se pesca por equipos de 2');

        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get('/admin/ranking?seccion=embarcacion')->assertOk()->assertSee('Los Lucios');
        $this->get("/admin/mangas/{$this->manga->id}/pesaje")->assertOk()->assertSee('Los Lucios')->assertSee('Alberto Rey / Toni Salgado');

        // Sin nombre propio, el equipo se pinta con un socio por línea, en la lista y en el cuadro.
        $this->get('/c/cd-pesca-piloto/embarcacion')->assertOk()->assertSee('Alberto Rey<br>Toni Salgado', escape: false);
        $this->get('/c/cd-pesca-piloto/manga/'.$this->manga->id)->assertOk()->assertSee('Alberto Rey<br>Toni Salgado', escape: false);

        // La tarjeta del podio se genera con los avatares de los dos socios.
        $ruta = Podio::deManga($this->manga);
        $this->assertFileExists($ruta);
        if ($destino = getenv('PODIO_GUARDAR')) {
            copy($ruta, $destino.'/podio-equipos.jpg');
        }
    }

    public function test_el_participante_sabe_como_pintarse(): void
    {
        $lucios = new Participante($this->lucios);
        $this->assertSame(['Los Lucios'], $lucios->lineas());
        $this->assertSame('Mario López / Sergio del Río', $lucios->detalleEquipo());
        $this->assertSame('Los Lucios', $lucios->nombreCorto());

        $barco = new Participante($this->barcoDos);
        $this->assertSame(['Alberto Rey', 'Toni Salgado'], $barco->lineas());
        $this->assertNull($barco->detalleEquipo());
        $this->assertSame('Alberto R. / Toni S.', $barco->nombreCorto());

        $socio = new Participante(Socio::where('nombre', 'Mario López')->firstOrFail());
        $this->assertSame(['Mario López'], $socio->lineas());
        $this->assertSame('Mario L.', $socio->nombreCorto());
        $this->assertFalse($socio->esEquipo);
    }

    public function test_un_equipo_con_historial_no_se_puede_borrar_y_uno_sin_el_si(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->sinManga->delete(); // sin participaciones: se borra
        $this->assertNull(Equipo::find($this->sinManga->id));
        $this->lucios->delete();   // con participaciones: la BD lo impide
    }

    public function test_el_socio_ve_su_equipo_en_el_inicio_con_el_tu(): void
    {
        $socio = Socio::where('nombre', 'Mario López')->firstOrFail();
        $participante = new Participante($this->lucios);
        $this->assertTrue($participante->incluye($socio->id));
        $this->assertSame(['Mario López', 'Sergio del Río'], $participante->socios->pluck('nombre')->all());
    }
}
