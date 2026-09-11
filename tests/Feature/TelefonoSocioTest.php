<?php

namespace Tests\Feature;

use App\Filament\Resources\Socios\Pages\CreateSocio;
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
 * Teléfono del socio: la ficha pide nombre, teléfono y email, y con el
 * teléfono «Dar acceso» abre directamente el chat de WhatsApp del socio.
 */
class TelefonoSocioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_al_crear_un_socio_se_pide_nombre_telefono_y_email_y_nada_mas(): void
    {
        Livewire::test(CreateSocio::class)
            ->assertFormFieldExists('nombre')
            ->assertFormFieldExists('telefono')
            ->assertFormFieldExists('email')
            ->assertFormFieldDoesNotExist('activo')
            ->fillForm(['nombre' => 'Nuevo Socio', 'telefono' => '  600  11 22 33 ', 'email' => 'Nuevo@Socio.ES'])
            ->call('create')
            ->assertHasNoFormErrors();

        $socio = Socio::where('nombre', 'Nuevo Socio')->firstOrFail();
        $this->assertSame('600 11 22 33', $socio->telefono);
        $this->assertSame('nuevo@socio.es', $socio->email);
        $this->assertSame('34600112233', $socio->numeroWhatsApp());
        $this->assertTrue($socio->activo);
    }

    public function test_un_telefono_que_no_lo_es_no_se_guarda(): void
    {
        Livewire::test(CreateSocio::class)
            ->fillForm(['nombre' => 'Otro Socio', 'telefono' => 'llámame'])
            ->call('create')
            ->assertHasFormErrors(['telefono']);

        // Sin teléfono se puede: es opcional.
        Livewire::test(CreateSocio::class)
            ->fillForm(['nombre' => 'Otro Socio'])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertNull(Socio::where('nombre', 'Otro Socio')->firstOrFail()->telefono);
    }

    public function test_con_telefono_el_whatsapp_del_acceso_abre_su_chat_y_sin_el_pide_elegir_contacto(): void
    {
        $paco = Socio::where('nombre', 'Paco Jiménez')->firstOrFail();
        $paco->update(['telefono' => '+34 611 22 33 44']);
        $andres = Socio::where('nombre', 'Andrés Molina')->firstOrFail();

        // Ficha del socio.
        $modal = (string) Livewire::test(ListSocios::class)
            ->mountAction(TestAction::make('acceso')->table($paco))
            ->instance()->getMountedAction()->getModalContent();
        $this->assertStringContainsString('href="https://wa.me/34611223344?text=', $modal);
        $this->assertStringContainsString('Enviar por WhatsApp a +34 611 22 33 44', $modal);
        $this->assertStringContainsString(rawurlencode($paco->fresh()->accessUrl()), $modal);

        $modal = (string) Livewire::test(ListSocios::class)
            ->mountAction(TestAction::make('acceso')->table($andres))
            ->instance()->getMountedAction()->getModalContent();
        $this->assertStringContainsString('href="https://wa.me/?text=', $modal);
        $this->assertStringContainsString('Sin teléfono en su ficha', $modal);

        // «Dar acceso»: Paco con enlace directo a su chat, Andrés a WhatsApp sin contacto.
        $modal = (string) Livewire::test(ListSocios::class)
            ->mountAction('enlaces')
            ->instance()->getMountedAction()->getModalContent();
        $this->assertStringContainsString('href="https://wa.me/34611223344?text=', $modal);
        $this->assertStringContainsString('+34 611 22 33 44', $modal);
        $this->assertStringContainsString('sin teléfono', $modal);
        $this->assertStringContainsString('les falta el teléfono', $modal);
        $this->assertStringContainsString('href="https://wa.me/?text=', $modal);
        $this->assertStringNotContainsString('navigator.share', $modal);
    }
}
