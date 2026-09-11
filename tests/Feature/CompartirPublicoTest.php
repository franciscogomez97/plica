<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Compartir;
use App\Services\Scoring;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enlaces públicos para WhatsApp: la clasificación de una sección o de una
 * manga la ve cualquiera con el enlace, sea o no del club, tenga o no el club
 * activado el perfil público.
 */
class CompartirPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_cada_seccion_tiene_un_slug_unico_dentro_del_club_que_no_cambia_al_renombrar(): void
    {
        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();

        $this->assertSame('orilla', Seccion::where('nombre', 'Orilla')->firstOrFail()->slug);
        $this->assertSame('pato-lucio', Seccion::where('nombre', 'Pato — Lucio')->firstOrFail()->slug);

        $repetida = Seccion::create(['club_id' => $club->id, 'nombre' => 'Orilla!']);
        $this->assertSame('orilla-2', $repetida->slug);

        $otro = Club::create(['nombre' => 'Otro', 'slug' => 'otro']);
        $this->assertSame('orilla', Seccion::create(['club_id' => $otro->id, 'nombre' => 'Orilla'])->slug);

        // Un enlace compartido no muere por renombrar la sección.
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $orilla->update(['nombre' => 'Orilla y costa']);
        $this->assertSame('orilla', $orilla->fresh()->slug);
    }

    public function test_la_seccion_es_publica_aunque_el_club_tenga_el_perfil_apagado(): void
    {
        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $club->update(['perfil_publico' => false]);

        $this->get('/c/cd-pesca-piloto/orilla')
            ->assertOk()
            ->assertSee('Ranking Orilla')
            ->assertSee('Mario López')
            ->assertSee('Manga a manga')
            ->assertSee('Compartir por WhatsApp')
            ->assertSee('Hecho con Plica')
            ->assertSee('property="og:title" content="Ranking Orilla · CD Pesca Piloto"', escape: false)
            ->assertSee('1º Mario López · 2º Sergio del Río · 3º Alberto Rey');

        // La portada del club sí respeta el interruptor; una sección que no existe, 404.
        $this->get('/c/cd-pesca-piloto')->assertNotFound();
        $this->get('/c/cd-pesca-piloto/no-existe')->assertNotFound();
    }

    public function test_la_manga_es_publica_y_solo_bajo_su_club(): void
    {
        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->firstOrFail();

        $this->get($manga->urlPublica())
            ->assertOk()
            ->assertSee($manga->nombre)
            ->assertSee('Orilla · por peso')
            ->assertSee('Mario López')
            ->assertSee('4,350 kg')
            ->assertSee('Compartir por WhatsApp')
            ->assertSee('Ver el ranking de Orilla');

        $otro = Club::create(['nombre' => 'Otro', 'slug' => 'otro']);
        $this->get("/c/otro/manga/{$manga->id}")->assertNotFound();
    }

    public function test_el_texto_para_whatsapp_lleva_el_podio_y_no_el_enlace(): void
    {
        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $temporada = Temporada::firstOrFail();
        $orilla = Scoring::rankingTemporada($temporada)->firstWhere('nombre', 'Orilla');

        $texto = Compartir::textoRanking($club, $temporada, $orilla);
        $this->assertStringContainsString("🏆 Ranking Orilla · Temporada 2026\nCD Pesca Piloto", $texto);
        $this->assertStringContainsString('1º Mario López · 6,450 kg', $texto);
        $this->assertStringContainsString('3º Alberto Rey · 2,650 kg', $texto);
        $this->assertStringContainsString('… y 1 más', $texto);
        $this->assertStringNotContainsString('http', $texto);

        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->firstOrFail();
        $texto = Compartir::textoManga($manga, Scoring::clasificacionManga($manga));
        $this->assertStringContainsString('🎣 1ª Manga · '.$manga->fecha->format('d/m/Y').' · Embalse de San Juan', $texto);
        $this->assertStringContainsString("Orilla:\n1º Mario López · 4,350 kg", $texto);
    }

    public function test_los_paneles_tienen_el_boton_de_compartir_con_el_enlace_publico(): void
    {
        $admin = User::where('email', 'admin@plica.test')->firstOrFail();
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->orderByDesc('fecha')->orderByDesc('id')->firstOrFail();

        $this->actingAs($admin)->get('/admin/ranking')->assertOk()
            ->assertSee('Compartir por WhatsApp')
            ->assertSee($orilla->urlPublica());
        $this->actingAs($admin)->get("/admin/ranking/{$orilla->id}")->assertOk()
            ->assertSee('Compartir por WhatsApp')
            ->assertSee('Ver la página pública');
        $this->actingAs($admin)->get("/admin/mangas/{$manga->id}/clasificacion")->assertOk()
            ->assertSee('Compartir por WhatsApp')
            ->assertSee($manga->urlPublica());

        $this->flushSession();
        $socio = User::where('email', 'socio@plica.test')->firstOrFail();
        $this->actingAs($socio)->get('/app')->assertOk()
            ->assertSee('Compartir por WhatsApp')
            ->assertSee($orilla->urlPublica())
            ->assertSee($manga->urlPublica());
    }
}
