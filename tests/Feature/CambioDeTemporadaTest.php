<?php

namespace Tests\Feature;

use App\Filament\Resources\Equipos\Pages\EditEquipo;
use App\Filament\Resources\Temporadas\Pages\CreateTemporada;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El cambio de temporada, «el sistema de enero», probado en septiembre viajando
 * en el tiempo: el aviso de diciembre, la temporada nueva en dos toques, los
 * socios y las secciones que siguen, los equipos que se copian y se pueden
 * retocar, y el historial del año anterior que no se toca.
 */
class CambioDeTemporadaTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Temporada $temporada2026;

    private Seccion $embarcacion;

    private Equipo $lucios;

    private Equipo $barcoDos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $this->temporada2026 = $this->club->temporadaActiva();
        $this->embarcacion = Seccion::where('nombre', 'Embarcación')->firstOrFail();
        $this->embarcacion->update(['modalidad' => Seccion::MODALIDAD_EQUIPOS]);
        $this->club->altaDeEquipos("Los Lucios: Mario López / Sergio del Río\nAlberto Rey / Toni Salgado", $this->embarcacion, $this->temporada2026);
        $this->lucios = Equipo::where('nombre', 'Los Lucios')->firstOrFail();
        $this->barcoDos = Equipo::whereNull('nombre')->firstOrFail();

        // Una manga de barcos en 2026 con pesaje: historial que tiene que quedarse quieto.
        $manga = Manga::create(['temporada_id' => $this->temporada2026->id, 'seccion_id' => $this->embarcacion->id, 'nombre' => 'Manga de barcos', 'fecha' => '2026-06-01', 'estado' => Manga::ESTADO_CELEBRADA]);
        $manga->participacions()->create(['equipo_id' => $this->lucios->id, 'seccion_id' => $this->embarcacion->id])->capturas()->create(['piezas' => 2, 'peso_gramos' => 5000]);
        $manga->participacions()->create(['equipo_id' => $this->barcoDos->id, 'seccion_id' => $this->embarcacion->id])->capturas()->create(['piezas' => 1, 'peso_gramos' => 3000]);

        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_el_aviso_de_temporada_nueva_sale_en_diciembre_y_en_enero_pero_no_en_septiembre(): void
    {
        Carbon::setTestNow('2026-09-15');
        $this->get('/admin')->assertOk()->assertDontSee('Crear la Temporada 2027');

        Carbon::setTestNow('2026-12-10');
        $this->get('/admin')->assertOk()->assertSee('Crear la Temporada 2027')->assertSee('nombre=Temporada%202027', escape: false);

        // Enero con la temporada de 2026 aún activa: el aviso sigue hasta que se crea.
        Carbon::setTestNow('2027-01-05');
        $this->get('/admin')->assertOk()->assertSee('Crear la Temporada 2027');
    }

    public function test_crear_la_temporada_nueva_activa_la_nueva_copia_los_equipos_y_no_toca_2026(): void
    {
        Carbon::setTestNow('2027-01-05');
        // Toni Salgado se ha dado de baja en diciembre: su barco se copia con uno solo.
        Socio::where('nombre', 'Toni Salgado')->firstOrFail()->update(['activo' => false]);

        Livewire::test(CreateTemporada::class)
            ->fillForm(['nombre' => 'Temporada 2027', 'activa' => true])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $nueva = Temporada::where('nombre', 'Temporada 2027')->firstOrFail();
        $this->assertTrue($nueva->activa);
        $this->assertFalse($this->temporada2026->fresh()->activa);
        $this->assertSame($nueva->id, $this->club->fresh()->temporadaActiva()->id);
        $this->get('/admin')->assertOk()->assertDontSee('Crear la Temporada 2027');

        // Socios y secciones siguen (son del club); los equipos se copiaron a 2027 con sus socios activos.
        $this->assertSame(12, $this->club->socios()->count());
        $this->assertSame(3, $this->club->seccions()->count());
        $copiados = $nueva->equipos()->with('socios')->get();
        $this->assertCount(2, $copiados);
        $lucios2027 = $copiados->firstWhere('nombre', 'Los Lucios');
        $this->assertSame(['Mario López', 'Sergio del Río'], $lucios2027->socios->pluck('nombre')->all());
        $barco2027 = $copiados->firstWhere('nombre', null);
        $this->assertSame(['Alberto Rey'], $barco2027->socios->pluck('nombre')->all(), 'el de baja no se copia');
        $this->get('/admin/equipos')->assertOk()->assertSee('Los Lucios')->assertSee('1 de 2')->assertSee('Temporada 2027');

        // 2026 sigue intacta: sus equipos, su manga y su pesaje.
        $this->assertCount(2, $this->temporada2026->fresh()->equipos);
        $this->assertSame(2, $this->lucios->fresh()->socios()->count());
        $this->assertSame(5000, $this->lucios->participacions()->first()->load('capturas')->pesoTotal());

        // La temporada nueva arranca vacía de mangas y de ranking.
        $this->assertSame(0, $nueva->mangas()->count());
        $this->get('/admin/ranking?seccion=embarcacion')->assertOk()->assertSee('Todavía no hay mangas celebradas');

        // Los equipos copiados aún no tienen capturas: se pueden retocar (Alberto recupera compañero).
        $chema = Socio::where('nombre', 'Chema Ortiz')->firstOrFail();
        $alberto = Socio::where('nombre', 'Alberto Rey')->firstOrFail();
        Livewire::test(EditEquipo::class, ['record' => $barco2027->getRouteKey()])
            ->fillForm(['socios' => [$alberto->id, $chema->id]])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['Alberto Rey', 'Chema Ortiz'], $barco2027->fresh()->socios->pluck('nombre')->all());
        $this->get('/admin/equipos')->assertOk()->assertSee('2 de 2')->assertDontSee('1 de 2');
    }

    public function test_un_equipo_que_ya_ha_pesado_no_cambia_de_socios_pero_si_de_nombre(): void
    {
        $chema = Socio::where('nombre', 'Chema Ortiz')->firstOrFail();
        $this->get("/admin/equipos/{$this->lucios->id}/edit")->assertOk()->assertSee('ya ha pesado alguna manga');

        Livewire::test(EditEquipo::class, ['record' => $this->lucios->getRouteKey()])
            ->fillForm(['nombre' => 'Los Lucios Negros', 'socios' => [$chema->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Los Lucios Negros', $this->lucios->fresh()->nombre);
        $this->assertSame(['Mario López', 'Sergio del Río'], $this->lucios->fresh()->socios->pluck('nombre')->all(), 'los socios no se tocan');
    }

    public function test_la_primera_temporada_de_un_club_no_copia_nada_y_no_falla(): void
    {
        $otro = Club::create(['nombre' => 'Club nuevo', 'slug' => 'club-nuevo']);
        $admin = User::create(['name' => 'Admin nuevo', 'email' => 'admin@nuevo.test', 'password' => bcrypt('secreta1234'), 'club_id' => $otro->id, 'role' => 'admin']);
        $this->actingAs($admin);

        Livewire::test(CreateTemporada::class)
            ->fillForm(['nombre' => 'Temporada 2027', 'activa' => true])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Temporada creada');

        $this->assertSame(1, $otro->temporadas()->count());
        $this->assertSame(0, Equipo::whereHas('temporada', fn ($q) => $q->where('club_id', $otro->id))->count());
    }

    public function test_crear_otra_temporada_no_duplica_equipos_donde_ya_los_hay(): void
    {
        $t2027 = Temporada::create(['club_id' => $this->club->id, 'nombre' => 'Temporada 2027', 'activa' => true]);
        $t2027->copiarEquiposDe($this->temporada2026);
        $this->assertCount(2, $t2027->equipos);

        // Segunda llamada (o alguien creando 2028 desde 2027): no se duplica lo que ya está.
        $this->assertSame(['equipos' => 0, 'incompletos' => 0], $t2027->fresh()->copiarEquiposDe($this->temporada2026));
        $this->assertCount(2, $t2027->fresh()->equipos);
    }
}
