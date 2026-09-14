<?php

namespace Tests\Feature;

use App\Filament\Resources\Mangas\Pages\ListMangas;
use App\Filament\Resources\Socios\Pages\CreateSocio;
use App\Filament\Resources\Socios\Pages\EditSocio;
use App\Filament\Resources\Socios\Pages\ListSocios;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\User;
use App\Services\Scoring;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Secciones por socio (14 de septiembre de 2026): un socio es de una o varias
 * secciones. Se aprende sola al pesarle y se corrige en su ficha; el ranking
 * de la sección lista también a quien no ha ido a ninguna manga (al final, con
 * sus puntos por ausencia) y a los de baja con su etiqueta; Socios y Mangas se
 * filtran por sección con pestañas que recuerdan dónde estaba cada admin.
 */
class SeccionesPorSocioTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Seccion $orilla;

    private Seccion $embarcacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $this->orilla = $this->club->seccions()->where('nombre', 'Orilla')->firstOrFail();
        $this->embarcacion = $this->club->seccions()->where('nombre', 'Embarcación')->firstOrFail();
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_pesar_a_un_socio_en_una_manga_le_hace_de_esa_seccion(): void
    {
        $socio = $this->club->socios()->create(['nombre' => 'Recién Llegado']);
        $this->assertSame([], $socio->seccions()->pluck('seccions.id')->all());

        $manga = $this->orilla->mangas()->firstOrFail();
        Participacion::create(['manga_id' => $manga->id, 'socio_id' => $socio->id, 'seccion_id' => $this->orilla->id, 'plica' => true]);

        $this->assertSame([$this->orilla->id], $socio->seccions()->pluck('seccions.id')->all());

        // Pesarle otra vez no lo duplica; pesarle en otra sección le añade esa.
        Participacion::create(['manga_id' => $this->orilla->mangas()->latest('id')->firstOrFail()->id, 'socio_id' => $socio->id, 'seccion_id' => $this->orilla->id, 'plica' => true]);
        Participacion::create(['manga_id' => $this->embarcacion->mangas()->firstOrFail()->id, 'socio_id' => $socio->id, 'seccion_id' => $this->embarcacion->id, 'plica' => true]);
        $this->assertEqualsCanonicalizing([$this->orilla->id, $this->embarcacion->id], $socio->seccions()->pluck('seccions.id')->all());
    }

    public function test_los_de_la_demo_ya_son_de_las_secciones_donde_han_pescado(): void
    {
        foreach (Participacion::whereNotNull('seccion_id')->get() as $p) {
            $this->assertTrue($p->socio->seccions->contains($p->seccion_id), "{$p->socio->nombre} en {$p->seccion->nombre}");
        }
    }

    public function test_un_socio_de_la_seccion_sin_mangas_sale_el_ultimo_con_sus_ausencias(): void
    {
        $socio = $this->club->socios()->create(['nombre' => 'Nunca Viene']);
        $socio->seccions()->attach($this->orilla->id);
        $mangas = $this->orilla->mangas()->where('estado', Manga::ESTADO_CELEBRADA)->count();
        $this->assertGreaterThan(0, $mangas);

        // Suma lo pescado: 0 puntos y 0 mangas, el último.
        $this->orilla->update(['sistema_puntuacion' => Seccion::SISTEMA_ACUMULADO, 'puntos_no_asistencia' => 0, 'descartes' => 0]);
        $fila = $this->filaDe('Nunca Viene');
        $this->assertSame(0, $fila->mangas);
        $this->assertSame(0, $fila->puntos);
        $this->assertSame($this->grupo()->filas->count(), $fila->puesto);
        $this->assertFalse($fila->baja);

        // Con puntos por ausencia (−100 por manga), los pierde todos.
        $this->orilla->update(['puntos_no_asistencia' => -100]);
        $this->assertSame(-100 * $mangas, $this->filaDe('Nunca Viene')->puntos);

        // Sistema de la federación: 48 fijos por manga.
        $this->orilla->update(['sistema_puntuacion' => Seccion::SISTEMA_PUESTOS, 'puntos_no_asistencia' => 48, 'desempate' => Seccion::DESEMPATE_PROMEDIO]);
        $this->assertSame(48 * $mangas, $this->filaDe('Nunca Viene')->puntos);

        // Y con un descarte que también descarta ausencias, una manga menos.
        $this->orilla->update(['descartes' => 1, 'descartes_ausencias' => true]);
        $this->assertSame(48 * ($mangas - 1), $this->filaDe('Nunca Viene')->puntos);

        // El cuadro manga a manga también lo lista, con todas las mangas en blanco.
        $cuadro = Scoring::cuadroSeccion($this->club->temporadaActiva(), $this->orilla->fresh());
        $fila = $cuadro->filas->first(fn (object $f) => $f->socio->nombre === 'Nunca Viene');
        $this->assertNotNull($fila);
        $this->assertSame(48 * ($mangas - 1), $fila->puntos);
    }

    public function test_quien_no_es_de_la_seccion_y_no_ha_pescado_no_sale(): void
    {
        $socio = $this->club->socios()->create(['nombre' => 'De Otra Sección']);
        $socio->seccions()->attach($this->embarcacion->id);

        $this->assertNull($this->filaDe('De Otra Sección'));
    }

    public function test_un_socio_de_baja_sigue_en_el_ranking_con_su_etiqueta(): void
    {
        $socio = $this->orilla->socios()->firstOrFail();
        $socio->update(['activo' => false]);

        $fila = $this->filaDe($socio->nombre);
        $this->assertNotNull($fila);
        $this->assertTrue($fila->baja);

        // Se ve en el ranking del panel y en el público.
        $this->get(route('filament.admin.pages.ranking'))->assertOk()->assertSee('Baja');
        $this->get(route('club.seccion', [$this->club->slug, $this->orilla->slug]))->assertOk()->assertSee('Baja');
    }

    public function test_en_socios_hay_una_pestana_por_seccion_y_se_recuerda(): void
    {
        $deOrilla = $this->orilla->socios()->firstOrFail();
        $soloEmbarcacion = $this->club->socios()->create(['nombre' => 'Solo Barco']);
        $soloEmbarcacion->seccions()->attach($this->embarcacion->id);

        Livewire::test(ListSocios::class)
            ->assertSee('Todos')
            ->assertSee('Orilla')
            ->assertSee('Embarcación')
            ->assertCanSeeTableRecords([$deOrilla, $soloEmbarcacion])
            ->set('activeTab', $this->orilla->slug)
            ->assertCanSeeTableRecords([$deOrilla])
            ->assertCanNotSeeTableRecords([$soloEmbarcacion]);

        // Al volver, sigue en Orilla.
        Livewire::test(ListSocios::class)
            ->assertSet('activeTab', $this->orilla->slug)
            ->assertCanNotSeeTableRecords([$soloEmbarcacion]);

        // Un club con una sola sección no tiene pestañas.
        $this->club->seccions()->where('id', '!=', $this->orilla->id)->get()->each(function (Seccion $s): void {
            $s->mangas()->get()->each->delete();
            $s->delete();
        });
        Livewire::test(ListSocios::class)->assertSet('activeTab', null);
    }

    public function test_en_mangas_tambien(): void
    {
        $deOrilla = $this->orilla->mangas()->firstOrFail();
        $deEmbarcacion = $this->embarcacion->mangas()->firstOrFail();

        Livewire::test(ListMangas::class)
            ->assertCanSeeTableRecords([$deOrilla, $deEmbarcacion])
            ->set('activeTab', $this->embarcacion->slug)
            ->assertCanSeeTableRecords([$deEmbarcacion])
            ->assertCanNotSeeTableRecords([$deOrilla]);

        // La pestaña de Mangas y la de Socios van cada una por su lado.
        Livewire::test(ListSocios::class)->assertSet('activeTab', 'todas');
        Livewire::test(ListMangas::class)->assertSet('activeTab', $this->embarcacion->slug);
    }

    public function test_desde_la_pestana_de_una_seccion_lo_nuevo_es_de_esa_seccion(): void
    {
        // «Nuevo socio» y «Nueva manga» llevan la sección en el enlace…
        Livewire::test(ListSocios::class)
            ->set('activeTab', $this->orilla->slug)
            ->assertSeeHtml('/admin/socios/create?seccion='.$this->orilla->id);
        Livewire::test(ListMangas::class)
            ->set('activeTab', $this->orilla->slug)
            ->assertSeeHtml('/admin/mangas/create?seccion='.$this->orilla->id);

        // Sin sección en el enlace, la ficha llega sin ninguna marcada (el club tiene tres)…
        Livewire::test(CreateSocio::class)->assertFormSet(['seccions' => []]);

        // …y con ella, marcada.
        Livewire::withQueryParams(['seccion' => $this->orilla->id])
            ->test(CreateSocio::class)
            ->assertFormSet(['seccions' => [$this->orilla->id]])
            ->fillForm(['nombre' => 'Alta Desde Orilla'])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame([$this->orilla->id], Socio::where('nombre', 'Alta Desde Orilla')->firstOrFail()->seccions()->pluck('seccions.id')->all());

        // «Añadir varios» desde la pestaña también los apunta.
        Livewire::test(ListSocios::class)
            ->set('activeTab', $this->embarcacion->slug)
            ->callAction('varios', ['lista' => "Uno De Barco\nDos De Barco"])
            ->assertHasNoActionErrors();
        foreach (['Uno De Barco', 'Dos De Barco'] as $nombre) {
            $this->assertSame([$this->embarcacion->id], Socio::where('nombre', $nombre)->firstOrFail()->seccions()->pluck('seccions.id')->all());
        }

        // Desde «Todos», sin sección.
        Livewire::test(ListSocios::class)
            ->set('activeTab', 'todas')
            ->callAction('varios', ['lista' => 'Tres Sin Sección']);
        $this->assertSame([], Socio::where('nombre', 'Tres Sin Sección')->firstOrFail()->seccions()->pluck('seccions.id')->all());
    }

    public function test_con_una_sola_seccion_en_el_club_todo_va_a_ella(): void
    {
        $this->club->seccions()->where('id', '!=', $this->orilla->id)->get()->each(function (Seccion $s): void {
            $s->mangas()->get()->each->delete();
            $s->delete();
        });

        Livewire::test(CreateSocio::class)->assertFormSet(['seccions' => [$this->orilla->id]]);

        Livewire::test(ListSocios::class)->callAction('varios', ['lista' => 'Único Posible']);
        $this->assertSame([$this->orilla->id], Socio::where('nombre', 'Único Posible')->firstOrFail()->seccions()->pluck('seccions.id')->all());
    }

    public function test_dar_acceso_desde_una_pestana_solo_lista_a_los_de_esa_seccion(): void
    {
        $soloEmbarcacion = $this->club->socios()->create(['nombre' => 'Solo Barco Acceso']);
        $soloEmbarcacion->seccions()->attach($this->embarcacion->id);

        $accion = Livewire::test(ListSocios::class)
            ->set('activeTab', $this->orilla->slug)
            ->mountAction('enlaces')
            ->instance()->getMountedAction();
        $this->assertSame('Dar acceso a los socios de Orilla', (string) $accion->getModalHeading());
        $this->assertStringNotContainsString('Solo Barco Acceso', (string) $accion->getModalContent());

        $accion = Livewire::test(ListSocios::class)
            ->set('activeTab', 'todas')
            ->mountAction('enlaces')
            ->instance()->getMountedAction();
        $this->assertSame('Dar acceso a los socios', (string) $accion->getModalHeading());
        $this->assertStringContainsString('Solo Barco Acceso', (string) $accion->getModalContent());
    }

    public function test_en_la_ficha_del_socio_se_marcan_sus_secciones(): void
    {
        $socio = $this->orilla->socios()->firstOrFail();

        Livewire::test(EditSocio::class, ['record' => $socio->id])
            ->assertFormSet(['seccions' => $socio->seccions()->pluck('seccions.id')->all()])
            ->fillForm(['seccions' => [$this->embarcacion->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$this->embarcacion->id], $socio->fresh()->seccions()->pluck('seccions.id')->all());
    }

    private function grupo(): object
    {
        return Scoring::rankingTemporada($this->club->temporadaActiva())->firstWhere('seccionId', $this->orilla->id);
    }

    private function filaDe(string $nombre): ?object
    {
        return $this->grupo()->filas->first(fn (object $f) => $f->socio->nombre === $nombre);
    }
}
