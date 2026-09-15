<?php

namespace Tests\Feature;

use App\Filament\Resources\Mangas\Pages\EditManga;
use App\Filament\Resources\Mangas\Pages\PesajeManga;
use App\Filament\Resources\Mangas\RelationManagers\ParticipacionsRelationManager;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Manga;
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
 * Paso 3 de las secciones por equipos: pasar lista y pesar por barcos. La
 * lista del pesaje son equipos, con sus casillas de siempre; el que viene sin
 * estar en la lista se añade desde el desplegable, igual que un socio.
 */
class PesajeEquiposTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Seccion $embarcacion;

    private Manga $manga;

    private Equipo $lucios;

    private Equipo $barcoDos;

    private Equipo $barcoTres;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $this->embarcacion = Seccion::where('nombre', 'Embarcación')->firstOrFail();
        $this->embarcacion->update(['modalidad' => Seccion::MODALIDAD_EQUIPOS]);
        $temporada = $this->club->temporadaActiva();
        $this->club->altaDeEquipos("Los Lucios: Mario López / Sergio del Río\nAlberto Rey / Toni Salgado\nPaco Jiménez / Iván Perea", $this->embarcacion, $temporada);
        $this->lucios = Equipo::where('nombre', 'Los Lucios')->firstOrFail();
        $this->barcoDos = Equipo::whereHas('socios', fn ($q) => $q->where('nombre', 'Alberto Rey'))->firstOrFail();
        $this->barcoTres = Equipo::whereHas('socios', fn ($q) => $q->where('nombre', 'Paco Jiménez'))->firstOrFail();
        $this->manga = Manga::create(['temporada_id' => $temporada->id, 'seccion_id' => $this->embarcacion->id, 'nombre' => 'Manga de barcos', 'fecha' => today(), 'estado' => Manga::ESTADO_PROGRAMADA]);

        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_pasar_lista_apunta_equipos_y_solo_quita_los_que_no_tienen_capturas(): void
    {
        $this->assertTrue($this->manga->porEquipos());

        $resultado = $this->manga->sincronizarAsistencia([$this->lucios->id, $this->barcoDos->id]);
        $this->assertSame(['creadas' => 2, 'eliminadas' => 0, 'bloqueadas' => []], $resultado);
        $this->assertSame([$this->lucios->id, $this->barcoDos->id], $this->manga->participacions()->orderBy('id')->pluck('equipo_id')->all());
        $this->assertNull($this->manga->participacions()->first()->socio_id);

        // Los Lucios ya tienen capturas: desmarcarlos no los quita.
        $this->manga->participacions()->where('equipo_id', $this->lucios->id)->first()->capturas()->create(['piezas' => 1, 'peso_gramos' => 1500]);
        $resultado = $this->manga->sincronizarAsistencia([$this->barcoTres->id]);
        $this->assertSame(2, $resultado['creadas'] + $resultado['eliminadas']); // entra el tres, sale el dos
        $this->assertSame(['Los Lucios'], $resultado['bloqueadas']);
        $this->assertEqualsCanonicalizing([$this->lucios->id, $this->barcoTres->id], $this->manga->participacions()->pluck('equipo_id')->all());
    }

    public function test_el_checklist_de_asistencia_lista_equipos_y_marca_los_que_tienen_algun_confirmado(): void
    {
        // Sergio (de Los Lucios) dijo que iría.
        $sergio = Socio::where('nombre', 'Sergio del Río')->firstOrFail();
        $this->manga->confirmacions()->create(['socio_id' => $sergio->id]);

        // El checklist son los equipos; arranca marcado el que tiene algún socio confirmado (como ConvocatoriaTest).
        Livewire::test(PesajeManga::class, ['record' => $this->manga->getRouteKey()])
            ->mountAction('asistencia')
            ->assertSee('Los Lucios')
            ->assertSee('Alberto Rey / Toni Salgado')
            ->assertActionDataSet(['socios' => [(string) $this->lucios->id]])
            ->setActionData(['socios' => [(string) $this->lucios->id, (string) $this->barcoTres->id]])
            ->callMountedAction()
            ->assertNotified();

        $this->assertEqualsCanonicalizing([$this->lucios->id, $this->barcoTres->id], $this->manga->participacions()->pluck('equipo_id')->all());
    }

    public function test_el_pesaje_lista_barcos_y_pesa_la_plica_del_barco(): void
    {
        $this->manga->sincronizarAsistencia([$this->lucios->id, $this->barcoDos->id]);
        $lucios = $this->manga->participacions()->where('equipo_id', $this->lucios->id)->firstOrFail();

        $componente = Livewire::test(PesajeManga::class, ['record' => $this->manga->getRouteKey()])
            ->assertSee('2 equipos')
            ->assertSee('Los Lucios')
            ->assertSee('Mario López / Sergio del Río') // debajo del nombre propio, quiénes son
            ->assertSee('Alberto Rey / Toni Salgado')
            ->assertSee('Elegir equipo…')
            ->assertSee('Iván Perea / Paco Jiménez') // el barco que falta, en el desplegable
            ->set("filas.{$lucios->id}.piezas", '3')
            ->set("filas.{$lucios->id}.peso", '6,400')
            ->assertSet("estados.{$lucios->id}.tipo", 'guardado');

        $this->assertSame(6400, $lucios->fresh()->load('capturas')->pesoTotal());
        $this->assertSame('Los Lucios', Scoring::clasificacionManga($this->manga)->first()->filas->first()->participante->nombre);

        // El barco que viene sin estar en la lista se añade desde el desplegable, y nunca dos veces.
        $componente->set('nuevoSocioId', (string) $this->barcoTres->id)->assertDispatched('pesaje-enfocar');
        $this->assertTrue($this->manga->participacions()->where('equipo_id', $this->barcoTres->id)->exists());
        $componente->set('nuevoSocioId', (string) $this->barcoTres->id);
        $this->assertSame(3, $this->manga->participacions()->count());

        // Un equipo de otra sección o de otro club, no.
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $orilla->update(['modalidad' => Seccion::MODALIDAD_EQUIPOS]);
        $ajeno = $orilla->equipos()->create(['temporada_id' => $this->club->temporadaActiva()->id, 'nombre' => 'De orilla']);
        $componente->set('nuevoSocioId', (string) $ajeno->id);
        $this->assertSame(3, $this->manga->participacions()->count());
    }

    public function test_la_ficha_de_la_manga_lista_las_participaciones_de_equipos(): void
    {
        $this->manga->sincronizarAsistencia([$this->lucios->id]);
        $participacion = $this->manga->participacions()->firstOrFail();

        // La pestaña se carga aparte (Livewire): se prueba el componente. La columna es el equipo, no un socio.
        Livewire::test(ParticipacionsRelationManager::class, ['ownerRecord' => $this->manga, 'pageClass' => EditManga::class])
            ->assertCanSeeTableRecords([$participacion])
            ->assertSee('Los Lucios');
    }
}
