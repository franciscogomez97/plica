<?php

namespace Tests\Feature;

use App\Filament\Resources\Mangas\MangaResource;
use App\Filament\Resources\Mangas\Pages\PesajeManga;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** La página de pesaje rápido: una casilla por dato y se guarda sola. */
class PesajeRapidoTest extends TestCase
{
    use RefreshDatabase;

    private Manga $manga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->firstOrFail();
    }

    private function participacionDe(string $nombre): Participacion
    {
        return $this->manga->participacions()
            ->whereHas('socio', fn ($q) => $q->where('nombre', $nombre))
            ->firstOrFail();
    }

    public function test_la_pagina_carga_con_los_asistentes_y_sus_totales(): void
    {
        $this->get("/admin/mangas/{$this->manga->id}/pesaje")
            ->assertOk()
            ->assertSee('Atrás')
            ->assertSee('Mario López')
            ->assertSee('Marcar asistencia')
            ->assertSee('Orilla · por peso');

        $mario = $this->participacionDe('Mario López');

        Livewire::test(PesajeManga::class, ['record' => $this->manga->getRouteKey()])
            ->assertSet("filas.{$mario->id}.piezas", '3')
            ->assertSet("filas.{$mario->id}.peso", '4350')
            ->assertSet("filas.{$mario->id}.mayor", '2100')
            ->assertSet("estados.{$mario->id}.texto", '3 piezas · 4,350 kg · mayor 2,100 kg');
    }

    public function test_al_salir_de_una_casilla_se_guarda_la_fila_por_peso(): void
    {
        $mario = $this->participacionDe('Mario López');

        Livewire::test(PesajeManga::class, ['record' => $this->manga->getRouteKey()])
            ->set("filas.{$mario->id}.piezas", '2')
            ->set("filas.{$mario->id}.peso", '3.450')
            ->assertSet("filas.{$mario->id}.peso", '3450')
            ->assertSet("estados.{$mario->id}.tipo", 'guardado')
            ->assertSet("estados.{$mario->id}.texto", '2 piezas · 3,450 kg · mayor 2,100 kg');

        // Una sola captura con el total: el pesaje rápido no apunta pez a pez.
        $capturas = $mario->fresh()->capturas;
        $this->assertCount(1, $capturas);
        $this->assertSame(2, $capturas[0]->piezas);
        $this->assertSame(3450, $capturas[0]->peso_gramos);
    }

    public function test_en_medida_cada_centimetro_es_un_pez(): void
    {
        // La 1ª manga de Pato — Lucio, por medida.
        $pato = Manga::whereHas('seccion', fn ($q) => $q->where('nombre', 'Pato — Lucio'))
            ->where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->firstOrFail();
        $andres = $pato->participacions()->whereHas('socio', fn ($q) => $q->where('nombre', 'Andrés Molina'))->firstOrFail();

        Livewire::test(PesajeManga::class, ['record' => $pato->getRouteKey()])
            ->set("filas.{$andres->id}.medidas", '58,5 62')
            // Por medida, la pieza mayor es el pez más largo y sale sola.
            ->assertSet("estados.{$andres->id}.texto", '2 peces · 120,5 cm · mayor 62 cm');

        $this->assertSame([585, 620], $andres->fresh()->capturas->pluck('medida_mm')->all());
    }

    public function test_vaciar_la_casilla_borra_las_capturas(): void
    {
        $mario = $this->participacionDe('Mario López');
        $this->assertTrue($mario->capturas()->exists());

        Livewire::test(PesajeManga::class, ['record' => $this->manga->getRouteKey()])
            ->set("filas.{$mario->id}.piezas", '')
            // Con solo las piezas vacías, el peso sigue ahí (y el admin lo ve en el total).
            ->assertSet("estados.{$mario->id}.texto", '0 piezas · 4,350 kg · mayor 2,100 kg')
            ->set("filas.{$mario->id}.peso", '')
            ->assertSet("estados.{$mario->id}.tipo", 'vacio')
            ->assertSet("estados.{$mario->id}.texto", 'Sin capturas');

        $this->assertFalse($mario->capturas()->exists());
    }

    public function test_un_dato_que_no_se_entiende_avisa_y_no_toca_nada(): void
    {
        $mario = $this->participacionDe('Mario López');
        $antes = $mario->capturas->pluck('peso_gramos')->all();

        Livewire::test(PesajeManga::class, ['record' => $this->manga->getRouteKey()])
            ->set("filas.{$mario->id}.peso", 'tres kilos')
            ->assertSet("estados.{$mario->id}.tipo", 'error')
            ->assertSet("filas.{$mario->id}.peso", 'tres kilos'); // se queda para corregirlo

        $this->assertSame($antes, $mario->fresh()->capturas->pluck('peso_gramos')->all());
    }

    public function test_quien_viene_sin_estar_en_la_lista_se_apunta_al_momento(): void
    {
        $sinApuntar = Socio::where('club_id', $this->manga->temporada->club_id)
            ->whereDoesntHave('participacions', fn ($q) => $q->where('manga_id', $this->manga->id))
            ->firstOrFail();
        $componente = Livewire::test(PesajeManga::class, ['record' => $this->manga->getRouteKey()])
            ->set('nuevoSocioId', (string) $sinApuntar->id)
            ->assertSet('nuevoSocioId', null)
            ->assertDispatched('pesaje-enfocar');

        // Compite en la sección de la manga: no hay nada que elegir.
        $participacion = $this->manga->participacions()->where('socio_id', $sinApuntar->id)->firstOrFail();
        $this->assertSame($this->manga->seccion_id, $participacion->seccion_id);
        $componente->assertSet("estados.{$participacion->id}.texto", 'Sin capturas');

        // Y si se apuntó por error, se quita mientras no tenga capturas.
        $componente->call('quitar', $participacion->id);
        $this->assertDatabaseMissing('participacions', ['id' => $participacion->id]);
    }

    public function test_no_se_puede_apuntar_a_un_socio_de_otro_club(): void
    {
        $otroClub = Club::create(['nombre' => 'Otro club', 'slug' => 'otro-club']);
        $intruso = Socio::create(['club_id' => $otroClub->id, 'nombre' => 'Intruso']);

        Livewire::test(PesajeManga::class, ['record' => $this->manga->getRouteKey()])
            ->set('nuevoSocioId', (string) $intruso->id);

        $this->assertFalse($this->manga->participacions()->where('socio_id', $intruso->id)->exists());
    }

    public function test_una_manga_de_otro_club_no_existe_para_este_admin(): void
    {
        $otroClub = Club::create(['nombre' => 'Otro club', 'slug' => 'otro-club']);
        $temporada = Temporada::create(['club_id' => $otroClub->id, 'nombre' => 'T', 'activa' => true]);
        $seccionAjena = Seccion::create(['club_id' => $otroClub->id, 'nombre' => 'Ajena']);
        $ajena = Manga::create(['temporada_id' => $temporada->id, 'seccion_id' => $seccionAjena->id, 'nombre' => 'Ajena', 'fecha' => today()]);

        $this->get("/admin/mangas/{$ajena->id}/pesaje")->assertNotFound();
    }

    public function test_cerrar_la_manga_la_marca_celebrada_y_lleva_a_la_clasificacion(): void
    {
        $programada = Manga::where('estado', Manga::ESTADO_PROGRAMADA)->firstOrFail();
        $socio = Socio::where('club_id', $programada->temporada->club_id)->firstOrFail();
        $programada->participacions()->create(['socio_id' => $socio->id, 'seccion_id' => $programada->seccion_id]);

        Livewire::test(PesajeManga::class, ['record' => $programada->getRouteKey()])
            ->callAction('celebrar')
            ->assertRedirect(MangaResource::getUrl('clasificacion', ['record' => $programada]));

        $this->assertSame(Manga::ESTADO_CELEBRADA, $programada->fresh()->estado);

        // Ya celebrada: el botón de cerrar desaparece.
        Livewire::test(PesajeManga::class, ['record' => $programada->getRouteKey()])
            ->assertActionHidden('celebrar');
    }

    public function test_la_lista_de_mangas_y_el_aviso_del_inicio_llevan_al_pesaje(): void
    {
        $pendiente = Manga::create([
            'temporada_id' => $this->manga->temporada_id,
            'seccion_id' => $this->manga->seccion_id,
            'nombre' => 'Manga de ayer',
            'fecha' => today()->subDay(),
        ]);

        $this->get('/admin')->assertOk()->assertSee("/admin/mangas/{$pendiente->id}/pesaje");
        $this->get('/admin/mangas')->assertOk()->assertSee("/admin/mangas/{$this->manga->id}/pesaje");
    }
}
