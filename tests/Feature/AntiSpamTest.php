<?php

namespace Tests\Feature;

use App\Mail\NuevaSolicitud;
use App\Services\AntiSpam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Los bots no llenan la bandeja: trampas silenciosas en el formulario y el WhatsApp fuera del HTML. */
class AntiSpamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['plica.notificaciones_email' => 'gestion@plica.test']);
    }

    /** Lo que envía una persona: el formulario pintado hace un rato, sin tocar el campo trampa. */
    private function persona(array $extra = []): array
    {
        return [
            'club_nombre' => 'CD Pesca Real',
            'email' => 'presidente@cdpescareal.es',
            'mensaje' => 'Somos 40 socios y llevamos las plicas en un Excel.',
            AntiSpam::CAMPO_SELLO => Crypt::encryptString((string) now()->subSeconds(30)->timestamp),
            AntiSpam::CAMPO_TRAMPA => '',
            ...$extra,
        ];
    }

    public function test_una_persona_pasa_y_se_avisa(): void
    {
        $this->post('/solicitud', $this->persona())->assertRedirect()->assertSessionHas('solicitud_ok');

        $this->assertDatabaseHas('solicituds', ['email' => 'presidente@cdpescareal.es']);
        Mail::assertSent(NuevaSolicitud::class, 1);
    }

    public function test_el_formulario_lleva_las_trampas_y_no_el_numero_de_whatsapp(): void
    {
        config(['plica.whatsapp' => '34600111222']);

        $html = $this->get('/')->assertOk()
            ->assertSee('name="'.AntiSpam::CAMPO_TRAMPA.'"', escape: false)
            ->assertSee('name="'.AntiSpam::CAMPO_SELLO.'"', escape: false)
            ->assertSee('Escríbenos por WhatsApp')
            ->assertSee(route('whatsapp'))
            ->assertDontSee('34600111222')
            ->assertDontSee('wa.me/34')
            ->getContent();

        // El sello se descifra y es de ahora.
        preg_match('/name="sello" value="([^"]+)"/', $html, $m);
        $this->assertEqualsWithDelta(now()->timestamp, (int) Crypt::decryptString($m[1]), 5);

        // La redirección sí lleva el número, y solo cuando hay número.
        $this->get('/whatsapp')->assertRedirect('https://wa.me/34600111222?text='.rawurlencode('Hola, soy de un club de pesca y quiero probar Plica.'));
        config(['plica.whatsapp' => null]);
        $this->get('/whatsapp')->assertNotFound();

        $this->assertStringContainsString('Disallow: /whatsapp', (string) file_get_contents(public_path('robots.txt')));
        $this->assertStringContainsString('Disallow: /acceso/', (string) file_get_contents(public_path('robots.txt')));
    }

    #[DataProvider('bots')]
    public function test_a_los_bots_se_les_dice_recibido_pero_ni_se_guarda_ni_se_avisa(array $extra): void
    {
        if (($extra[AntiSpam::CAMPO_SELLO] ?? null) === 'ahora') {
            $extra[AntiSpam::CAMPO_SELLO] = Crypt::encryptString((string) now()->timestamp); // pintado y enviado en el mismo segundo
        }

        $this->post('/solicitud', $this->persona($extra))->assertRedirect()->assertSessionHas('solicitud_ok');

        $this->assertDatabaseCount('solicituds', 0);
        Mail::assertNothingSent();
    }

    public static function bots(): array
    {
        return [
            'rellena el campo trampa' => [[AntiSpam::CAMPO_TRAMPA => 'https://spam.example']],
            'envía al instante' => [[AntiSpam::CAMPO_SELLO => 'ahora']],
            'sin sello' => [[AntiSpam::CAMPO_SELLO => '']],
            'sello inventado' => [[AntiSpam::CAMPO_SELLO => 'no-es-un-sello']],
            'mete enlaces en el mensaje' => [['mensaje' => 'Buy followers at https://spam.example now']],
            'mete www en el mensaje' => [['mensaje' => 'visita www.spam.example']],
        ];
    }

    public function test_el_mismo_email_dos_veces_en_un_dia_solo_cuenta_una(): void
    {
        $this->post('/solicitud', $this->persona())->assertSessionHas('solicitud_ok');
        $this->post('/solicitud', $this->persona(['mensaje' => 'Otra vez']))->assertSessionHas('solicitud_ok');

        $this->assertDatabaseCount('solicituds', 1);
        Mail::assertSent(NuevaSolicitud::class, 1);

        // Al día siguiente, sí.
        Carbon::setTestNow(now()->addDay()->addMinute());
        $this->post('/solicitud', $this->persona(['mensaje' => 'Seguimos interesados']))->assertSessionHas('solicitud_ok');
        $this->assertDatabaseCount('solicituds', 2);
        Carbon::setTestNow();
    }

    public function test_hay_limite_de_envios_por_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/solicitud', $this->persona(['email' => "club{$i}@test.es"]))->assertRedirect();
        }

        $this->post('/solicitud', $this->persona(['email' => 'club6@test.es']))->assertStatus(429);
    }
}
