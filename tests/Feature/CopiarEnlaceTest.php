<?php

namespace Tests\Feature;

use App\Filament\Resources\Socios\Pages\ListSocios;
use App\Models\Socio;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * «Copiar» el enlace de acceso tiene que funcionar también por IP y http, sin
 * HTTPS: ahí navigator.clipboard no existe y el botón decía «✔» sin copiar
 * nada, y el admin pegaba lo que tuviera de antes (la IP) y caía en la landing.
 */
class CopiarEnlaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_el_panel_del_club_lleva_el_copiar_con_fallback_sin_https(): void
    {
        $this->get('/admin/socios')->assertOk()
            ->assertSee('window.plicaCopiar', escape: false)
            ->assertSee('isSecureContext', escape: false)
            ->assertSee("execCommand('copy')", escape: false)
            ->assertSee('copia a mano');
    }

    public function test_el_enlace_de_un_socio_es_el_de_acceso_y_se_copia_con_el_fallback(): void
    {
        $socio = Socio::where('nombre', 'Paco Jiménez')->firstOrFail();

        $componente = Livewire::test(ListSocios::class)
            ->mountAction(TestAction::make('acceso')->table($socio));

        $modal = (string) $componente->instance()->getMountedAction()->getModalContent();
        $url = $socio->fresh()->accessUrl();

        $this->assertStringContainsString('/acceso/', $url);
        $this->assertStringContainsString('value="'.$url.'"', $modal);
        $this->assertStringContainsString('plicaCopiar(this,', $modal);
        $this->assertStringNotContainsString('navigator.clipboard', $modal);
        $this->assertStringContainsString('wa.me/?text=', $modal);
    }

    public function test_los_enlaces_en_bloque_tambien_copian_con_el_fallback(): void
    {
        $modal = (string) Livewire::test(ListSocios::class)
            ->mountAction('enlaces')
            ->instance()->getMountedAction()->getModalContent();

        $this->assertStringContainsString("plicaCopiar(this, this.dataset.texto, 'Copiados ✓')", $modal);
        $this->assertStringContainsString("plicaCopiar(this, this.dataset.texto, 'Copiado ✓')", $modal);
        $this->assertStringNotContainsString('navigator.clipboard', $modal);
    }
}
