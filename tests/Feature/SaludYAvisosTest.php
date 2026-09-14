<?php

namespace Tests\Feature;

use App\Mail\Aviso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lo que vigila el servidor: /salud para el monitor externo y plica:avisar
 * para que las copias (o cualquier cosa) avisen por correo.
 */
class SaludYAvisosTest extends TestCase
{
    use RefreshDatabase;

    public function test_salud_responde_200_con_la_base_de_datos_viva_y_sin_cache(): void
    {
        $this->get('/salud')
            ->assertOk()
            ->assertJson(['ok' => true, 'bd' => 'ok'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertStringContainsString('Disallow: /salud', file_get_contents(public_path('robots.txt')));
    }

    public function test_avisar_manda_un_correo_al_email_de_notificaciones(): void
    {
        Mail::fake();
        config(['plica.notificaciones_email' => 'avisos@plica.test']);

        $this->artisan('plica:avisar', ['asunto' => 'Copia de seguridad FALLIDA', 'texto' => 'Mira el log.'])
            ->assertSuccessful()
            ->expectsOutputToContain('enviado a avisos@plica.test');

        Mail::assertSent(Aviso::class, fn (Aviso $mail) => $mail->hasTo('avisos@plica.test') && $mail->hasSubject('[Plica] Copia de seguridad FALLIDA') && $mail->texto === 'Mira el log.');
        $this->assertStringContainsString('Mira el log.', (new Aviso('Copia de seguridad FALLIDA', 'Mira el log.'))->render());

        // A otro destinatario, y sin texto usa el asunto.
        $this->artisan('plica:avisar', ['asunto' => 'Prueba', '--para' => 'yo@plica.test'])->assertSuccessful();
        Mail::assertSent(Aviso::class, fn (Aviso $mail) => $mail->hasTo('yo@plica.test') && $mail->hasSubject('[Plica] Prueba') && $mail->texto === 'Prueba');

        // Sin nadie a quien avisar, lo dice y falla.
        config(['plica.notificaciones_email' => null]);
        $this->artisan('plica:avisar', ['asunto' => 'Nadie'])->assertFailed();
    }
}
