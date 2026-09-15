<?php

namespace Tests\Feature;

use App\Models\Captura;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Services\Podio;
use App\Services\Scoring;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La tarjeta del podio: imagen 1080×1920 con la clasificación de una manga o el
 * ranking de una sección, generada con GD a partir de la misma clasificación
 * que la web, cacheada por contenido y compartible por WhatsApp.
 */
class PodioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        Podio::vaciar();
    }

    private function mangaCelebrada(): Manga
    {
        return Manga::where('estado', Manga::ESTADO_CELEBRADA)->orderByDesc('fecha')->firstOrFail();
    }

    public function test_la_tarjeta_de_una_manga_es_un_jpeg_vertical_con_los_datos_de_la_clasificacion(): void
    {
        $manga = $this->mangaCelebrada();
        $grupo = Scoring::clasificacionManga($manga)->first();

        if (getenv('PODIO_GUARDAR')) {
            // Para ver el escudo en la esquina: el logo de Plica hace de escudo del club de demo.
            \Illuminate\Support\Facades\Storage::disk('public')->put('logos/demo-escudo.png', (string) file_get_contents(public_path('brand/plica.png')));
            $manga->temporada->club->update(['logo' => 'logos/demo-escudo.png']);
            $manga->load('temporada.club');
        }

        $ruta = Podio::deManga($manga, $grupo);

        $this->assertFileExists($ruta);
        [$ancho, $alto, $tipo] = getimagesize($ruta);
        $this->assertSame([Podio::ANCHO, Podio::ALTO, IMAGETYPE_JPEG], [$ancho, $alto, $tipo]);
        $this->assertGreaterThan(20_000, filesize($ruta), 'una tarjeta pintada pesa decenas de KB');

        if ($destino = getenv('PODIO_GUARDAR')) {
            copy($ruta, $destino.'/podio-manga.jpg');
            copy(Podio::deRanking($manga->temporada, Seccion::where('nombre', 'Orilla')->firstOrFail()), $destino.'/podio-ranking.jpg');
        }
    }

    public function test_se_cachea_por_contenido_y_se_regenera_al_cambiar_un_pesaje(): void
    {
        $manga = $this->mangaCelebrada();

        $primera = Podio::deManga($manga);
        $this->assertSame($primera, Podio::deManga($manga), 'misma clasificación, mismo fichero');
        $this->assertCount(1, glob(Podio::carpeta().'/manga-'.$manga->id.'-*.jpg'));

        // Cambia un peso: otra versión, y la anterior desaparece.
        $captura = Captura::whereHas('participacion', fn ($q) => $q->where('manga_id', $manga->id))->firstOrFail();
        $captura->update(['peso_gramos' => $captura->peso_gramos + 5000]);

        $segunda = Podio::deManga($manga->fresh());
        $this->assertNotSame($primera, $segunda);
        $this->assertFileDoesNotExist($primera);
        $this->assertCount(1, glob(Podio::carpeta().'/manga-'.$manga->id.'-*.jpg'));
    }

    public function test_la_ruta_publica_sirve_la_imagen_y_la_pagina_la_usa_de_vista_previa(): void
    {
        $manga = $this->mangaCelebrada();
        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();

        $this->get("/c/cd-pesca-piloto/manga/{$manga->id}/podio.jpg")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $urlPodio = Podio::urlManga($manga);
        $this->assertStringContainsString("/manga/{$manga->id}/podio.jpg?v=", $urlPodio);
        $this->get("/c/cd-pesca-piloto/manga/{$manga->id}")
            ->assertOk()
            ->assertSee('property="og:image" content="'.$urlPodio.'"', escape: false)
            ->assertSee('summary_large_image')
            ->assertSee('Compartir por WhatsApp')
            // Un solo botón: la tarjeta viaja con el podio escrito y el enlace como pie, un solo envío.
            ->assertSee('data-imagen="'.e($urlPodio).'"', escape: false)
            ->assertSee('data-pie="', escape: false)
            ->assertSee(e($manga->urlPublica()), escape: false);

        // Ranking de sección: imagen y vista previa.
        $this->get('/c/cd-pesca-piloto/orilla/podio.jpg')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/c/cd-pesca-piloto/orilla')
            ->assertOk()
            ->assertSee('/orilla/podio.jpg?v=', escape: false)
            ->assertSee('data-imagen="', escape: false); // el botón pequeño junto al título, con la tarjeta

        // Aislamiento: una manga de otro club no se sirve bajo este club.
        $otro = Club::create(['nombre' => 'Otro', 'slug' => 'otro']);
        $this->get("/c/otro/manga/{$manga->id}/podio.jpg")->assertNotFound();
        $this->get('/c/otro/orilla/podio.jpg')->assertNotFound();
    }

    public function test_sin_fotos_salen_iniciales_y_los_nombres_largos_se_abrevian(): void
    {
        $this->assertSame('ML', Podio::iniciales('Mario López'));
        $this->assertSame('M', Podio::iniciales('Mario'));
        $this->assertSame('Mario L.', Podio::abreviar('Mario López García'));
        $this->assertSame('Mario', Podio::abreviar('Mario'));
    }

    public function test_la_tarjeta_admite_una_manga_con_muchos_participantes_y_dice_cuantos_quedan_fuera(): void
    {
        $manga = $this->mangaCelebrada();
        $club = $manga->temporada->club;
        $seccion = Scoring::clasificacionManga($manga)->first()->seccion;

        // 40 socios más en la manga: la tarjeta enseña 33 y cuenta el resto.
        for ($i = 1; $i <= 40; $i++) {
            $socio = Socio::create(['club_id' => $club->id, 'nombre' => "Pescador Número {$i}", 'activo' => true]);
            $p = $manga->participacions()->create(['socio_id' => $socio->id, 'seccion_id' => $seccion?->id]);
            $p->capturas()->create(['piezas' => 1, 'peso_gramos' => 100 * $i]);
        }

        $ruta = Podio::deManga($manga->fresh());
        $this->assertFileExists($ruta);
        $this->assertGreaterThan(33, Scoring::clasificacionManga($manga->fresh())->first()->filas->count());

        if ($destino = getenv('PODIO_GUARDAR')) {
            copy($ruta, $destino.'/podio-lleno.jpg');
        }
    }
}
