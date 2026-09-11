<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AntiSpam;
use App\Support\Marca;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/** La landing, las páginas legales y la marca de Plica en todas partes. */
class LandingYLegalTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_landing_cuenta_el_producto_sin_precio_y_recoge_solicitudes(): void
    {
        $this->seed(DemoSeeder::class);

        $this->get('/')->assertOk()
            ->assertSee('sin Excel y sin líos de WhatsApp')
            ->assertSee('Así es una manga con Plica')
            ->assertSee('Pesas en tres minutos')
            ->assertSee('Hecho para clubes de pesca')
            ->assertDontSee('clubes deportivos')
            ->assertSee('Empieza con lo que ya tienes')
            ->assertSee('vuestro Excel')
            // El precio está oculto de momento: ni cifra ni «gratis hasta».
            ->assertDontSee('150 €')
            ->assertDontSee('Gratis hasta')
            ->assertDontSee('fundadores')
            ->assertSee('Lo que preguntan los presidentes')
            ->assertSee('Solicita acceso')
            ->assertSee('Ver un club de ejemplo') // hay club de demo con portada pública
            ->assertSee(Marca::LOGO)
            ->assertSee('property="og:image" content="'.asset(Marca::OG).'"', escape: false)
            ->assertSee('Aviso legal')
            ->assertSee('Privacidad')
            ->assertSee('Cookies')
            ->assertSee('Condiciones');

        // El formulario sigue funcionando.
        $this->post('/solicitud', ['club_nombre' => 'CD Test', 'email' => 'test@test.es', AntiSpam::CAMPO_SELLO => Crypt::encryptString((string) now()->subSeconds(30)->timestamp)])->assertRedirect();
        $this->assertDatabaseHas('solicituds', ['club_nombre' => 'CD Test']);
    }

    public function test_la_landing_atiende_por_whatsapp_y_no_por_videollamada(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('videollamada')
            ->assertSee('te atendemos por WhatsApp')
            ->assertDontSee('wa.me/');

        config(['plica.whatsapp' => '34600111222']);
        $this->get('/')->assertOk()
            ->assertSee('Escríbenos por WhatsApp')
            ->assertSee(route('whatsapp'))       // el número no va en el HTML: redirige al pulsar
            ->assertDontSee('34600111222');
    }

    public function test_sin_club_de_demo_la_landing_no_enlaza_a_un_ejemplo_que_no_existe(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Ver un club de ejemplo');
    }

    public function test_las_paginas_legales_cargan_con_el_titular_configurado(): void
    {
        config(['plica.legal.titular' => 'Blinders Group SL', 'plica.legal.nif' => 'B12345678', 'plica.legal.email' => 'hola@plica.test']);

        $this->get('/aviso-legal')->assertOk()->assertSee('Aviso legal')->assertSee('Blinders Group SL')->assertSee('B12345678')->assertSee('LSSI-CE');
        $this->get('/privacidad')->assertOk()->assertSee('Política de privacidad')->assertSee('encargado del tratamiento')->assertSee('hola@plica.test')->assertSee('aepd.es');
        $this->get('/cookies')->assertOk()->assertSee('solo cookies técnicas')->assertSee('plica_session')->assertSee('XSRF-TOKEN');
        // El precio está oculto también en condiciones (igual que en la landing): periodo de lanzamiento gratuito.
        $this->get('/condiciones')->assertOk()->assertSee('gratuito')->assertDontSee('150 €')->assertDontSee('otoño de 2026')->assertDontSee('fundadores')->assertSee('Nunca se borran datos por impago')->assertSee('Anexo: contrato de encargo del tratamiento')->assertSee('art. 28 RGPD');
    }

    public function test_sin_titular_configurado_se_ve_que_falta_rellenarlo(): void
    {
        $this->get('/aviso-legal')->assertOk()->assertSee('[Nombre o razón social del titular]');
    }

    public function test_nif_y_direccion_son_opcionales_mientras_no_haya_actividad_economica(): void
    {
        // Sin dato, la línea no se pinta (nada de corchetes en una web pública).
        config(['plica.legal.titular' => 'Francisco Gómez', 'plica.legal.nif' => null, 'plica.legal.direccion' => null]);
        $this->get('/aviso-legal')->assertOk()
            ->assertSee('Francisco Gómez')
            ->assertDontSee('<dt>NIF</dt>', escape: false)
            ->assertDontSee('<dt>Domicilio</dt>', escape: false)
            ->assertDontSee('[NIF]')
            ->assertDontSee('[Dirección postal]');

        // Con dato, sale.
        config(['plica.legal.nif' => '12345678Z', 'plica.legal.direccion' => 'Apartado de correos 1, 28001 Madrid']);
        $this->get('/aviso-legal')->assertOk()
            ->assertSee('<dt>NIF</dt>', escape: false)->assertSee('12345678Z')
            ->assertSee('<dt>Domicilio</dt>', escape: false)->assertSee('Apartado de correos 1');
    }

    public function test_la_marca_de_plica_esta_en_el_login_los_iconos_y_el_manifiesto(): void
    {
        $this->seed(DemoSeeder::class);

        $this->get('/app/login')->assertOk()
            ->assertSee(Marca::LOGO)
            ->assertSee('favicon-32.png')
            ->assertSee('apple-touch-icon.png')
            // Login todo gris: sin cuadro gris sobre fondo negro en modo oscuro.
            ->assertSee('.dark .fi-simple-layout { background: #27272a; }', escape: false);

        foreach (['favicon-16', 'favicon-32', 'apple-touch-icon', 'icon-192', 'icon-512', 'icon-maskable-512'] as $icono) {
            $this->assertFileExists(public_path("icons/{$icono}.png"));
        }
        $this->assertFileExists(public_path('brand/plica-256.webp'));
        $this->assertFileExists(public_path('brand/og.png'));
        $this->assertStringContainsString('icon-maskable-512.png', (string) file_get_contents(public_path('manifest.webmanifest')));

        // Un admin de un club sin logo ve el nombre de su club; el logo de Plica queda para la web y el login.
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        $this->get('/admin')->assertOk()->assertSee('CD Pesca Piloto');
    }
}
