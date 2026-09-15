<?php

namespace Tests\Feature;

use App\Filament\Resources\Mangas\Pages\EditManga;
use App\Models\Manga;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * «Compartir por WhatsApp» abre WhatsApp, no la hoja de compartir del sistema:
 * la gente espera que salga WhatsApp y punto. En todas partes: páginas
 * públicas, paneles y la convocatoria. La única excepción es «Compartir
 * imagen» (la tarjeta del podio): un enlace wa.me no puede llevar un fichero,
 * así que ahí sí se usa la hoja del sistema, que en el móvil ofrece WhatsApp.
 */
class WhatsAppDirectoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_las_paginas_publicas_abren_whatsapp_directamente(): void
    {
        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->firstOrFail();

        foreach (['/c/cd-pesca-piloto/orilla', '/c/cd-pesca-piloto/manga/'.$manga->id] as $url) {
            $html = $this->get($url)->assertOk()
                ->assertSee('href="https://wa.me/?text=', escape: false)
                ->assertSee('Compartir') // en la sección, el botón compacto; en la manga, el largo
                ->assertSee('podio.jpg?v=', escape: false) // el botón de imagen, aparte
                ->getContent();
            // La hoja de compartir del sistema solo la usa el botón de imagen, nunca el de WhatsApp.
            $this->assertSame(1, substr_count($html, 'navigator.share('), 'una sola llamada a la hoja del sistema: la de la imagen');
            $this->assertStringNotContainsString('navigator.share', substr($html, 0, strpos($html, 'compartir-imagen') ?: strpos($html, 'podio.jpg')));
        }
    }

    public function test_los_paneles_abren_whatsapp_directamente(): void
    {
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        $html = $this->get('/admin/ranking?seccion=orilla')->assertOk()
            ->assertSee('href="https://wa.me/?text=', escape: false)
            ->getContent();
        $this->assertSame(1, substr_count($html, 'navigator.share('), 'una sola llamada a la hoja del sistema: la de la imagen');
    }

    public function test_la_convocatoria_abre_whatsapp_con_el_texto_del_cuadro(): void
    {
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $proxima = Manga::where('estado', Manga::ESTADO_PROGRAMADA)->orderBy('fecha')->firstOrFail();

        $modal = (string) Livewire::test(EditManga::class, ['record' => $proxima->getRouteKey()])
            ->mountAction('convocar')
            ->instance()->getMountedAction()->getModalContent();

        $this->assertStringContainsString("'https://wa.me/?text=' + encodeURIComponent(", $modal);
        $this->assertStringNotContainsString('navigator.share', $modal);
    }
}
