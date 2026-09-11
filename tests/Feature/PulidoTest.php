<?php

namespace Tests\Feature;

use App\Filament\Resources\Socios\Pages\ListSocios;
use App\Filament\Widgets\NuevaTemporadaWidget;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pulido de septiembre de 2026: sección obligatoria, enlaces de acceso en
 * bloque, portada pública resumida, app instalable y aviso de temporada.
 */
class PulidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_toda_manga_es_de_una_seccion_y_una_seccion_con_mangas_no_se_borra(): void
    {
        $temporada = Temporada::firstOrFail();

        $this->expectException(QueryException::class);
        Manga::create(['temporada_id' => $temporada->id, 'nombre' => 'Sin sección', 'fecha' => today()]);
    }

    public function test_la_base_de_datos_impide_borrar_una_seccion_con_mangas(): void
    {
        $seccion = Seccion::has('mangas')->firstOrFail();

        $this->expectException(QueryException::class);
        $seccion->delete();
    }

    public function test_el_admin_saca_los_enlaces_de_acceso_de_todos_de_una_vez(): void
    {
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $sinToken = Socio::where('club_id', 1)->where('activo', true)->whereNull('invite_token')->count();
        $this->assertGreaterThan(0, $sinToken);

        $componente = Livewire::test(ListSocios::class)
            ->assertActionVisible('enlaces')
            ->mountAction('enlaces');

        $modal = (string) $componente->instance()->getMountedAction()->getModalContent();
        $this->assertStringContainsString('Copiar todos los mensajes', $modal);
        $this->assertStringContainsString('Mario López', $modal);
        $this->assertStringContainsString('Con cuenta', $modal);
        $this->assertStringContainsString('Sin cuenta', $modal);
        $this->assertStringContainsString('/acceso/', $modal);

        // Todos los activos tienen ya su enlace; los de baja, no.
        $this->assertSame(0, Socio::where('club_id', 1)->where('activo', true)->whereNull('invite_token')->count());
    }

    public function test_la_portada_publica_resume_cada_seccion_y_enlaza_al_completo(): void
    {
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();

        $this->get('/c/cd-pesca-piloto')->assertOk()
            ->assertSee('Ranking Orilla')
            ->assertSee('mangas celebradas')
            ->assertSee('Ver ranking completo y manga a manga')
            ->assertSee($orilla->urlPublica())
            ->assertSee('Pieza mayor de la temporada')
            ->assertSee('Ver la clasificación completa')
            ->assertSee('y 1 más')          // Orilla tiene 4: se ve el podio
            ->assertDontSee('Toni Salgado'); // el 4º no está en el resumen
    }

    public function test_la_pagina_de_seccion_no_enlaza_a_una_portada_apagada(): void
    {
        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();

        $this->get('/c/cd-pesca-piloto/orilla')->assertOk()->assertSee('href="'.route('club.publico', $club).'"', escape: false);

        $club->update(['perfil_publico' => false]);
        $this->get('/c/cd-pesca-piloto/orilla')->assertOk()
            ->assertSee('CD Pesca Piloto')
            ->assertDontSee('href="'.route('club.publico', $club).'"', escape: false);
    }

    public function test_la_app_se_puede_instalar_en_el_movil(): void
    {
        // Ficheros estáticos (los sirve el servidor web, no Laravel): existen y el manifiesto es válido.
        $manifiesto = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('Plica', $manifiesto['name']);
        $this->assertSame('/app', $manifiesto['start_url']);
        $this->assertSame('standalone', $manifiesto['display']);
        foreach ($manifiesto['icons'] as $icono) {
            $this->assertFileExists(public_path($icono['src']));
        }

        $this->get('/')->assertOk()->assertSee('rel="manifest"', escape: false);

        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail());
        $this->get('/app')->assertOk()
            ->assertSee('rel="manifest"', escape: false)
            ->assertSee('apple-touch-icon')
            // Y se le cuenta cómo: el aviso «Lleva Plica en el móvil» con el botón Instalar / el camino de Safari.
            ->assertSee('Lleva Plica en el móvil')
            ->assertSee('Añadir a pantalla de inicio')
            ->assertSee('beforeinstallprompt');

        $this->flushSession();
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        $this->get('/admin')->assertOk()->assertSee('Lleva Plica en el móvil');
    }

    public function test_en_diciembre_el_inicio_propone_crear_la_temporada_siguiente(): void
    {
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // En septiembre, nada.
        Carbon::setTestNow('2026-09-11');
        $this->assertFalse(NuevaTemporadaWidget::canView());

        // En diciembre, el aviso con el nombre ya puesto.
        Carbon::setTestNow('2026-12-05');
        $this->assertTrue(NuevaTemporadaWidget::canView());
        $this->get('/admin')->assertOk()
            ->assertSee('está acabando')
            ->assertSee('Crear la Temporada 2027')
            ->assertSee('nombre=Temporada%202027', escape: false);
        $this->get('/admin/temporadas/create?nombre=Temporada+2027')->assertOk()->assertSee('Temporada 2027');

        // En cuanto existe la siguiente, desaparece.
        Temporada::create(['club_id' => 1, 'nombre' => 'Temporada 2027', 'activa' => false]);
        $this->assertFalse(NuevaTemporadaWidget::canView());

        // Y si la activa es de un año ya pasado, avisa aunque no sea diciembre.
        Temporada::where('nombre', 'Temporada 2027')->delete();
        Carbon::setTestNow('2027-02-01');
        $this->assertTrue(NuevaTemporadaWidget::canView());

        Carbon::setTestNow();
    }
}
