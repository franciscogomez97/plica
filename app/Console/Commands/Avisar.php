<?php

namespace App\Console\Commands;

use App\Mail\Aviso;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Manda un aviso por correo al email de notificaciones de Plica (el del
 * superadmin si no hay otro). Lo usa el script de copias del VPS cuando algo
 * falla, y sirve para probar que el correo sale de verdad:
 *
 *   php artisan plica:avisar "Prueba de correo" "Si lees esto, el SMTP funciona."
 */
class Avisar extends Command
{
    protected $signature = 'plica:avisar
        {asunto : Asunto del correo}
        {texto? : Cuerpo del correo (por defecto, el asunto)}
        {--para= : Otro destinatario (por defecto, el email de notificaciones de Plica)}';

    protected $description = 'Manda un aviso por correo al email de notificaciones de Plica';

    public function handle(): int
    {
        $para = $this->option('para') ?: config('plica.notificaciones_email');

        if (blank($para)) {
            $this->error('No hay a quién avisar: pon PLICA_NOTIFICACIONES_EMAIL o PLICA_SUPERADMIN_EMAIL en el .env.');

            return self::FAILURE;
        }

        $asunto = trim($this->argument('asunto'));
        $texto = trim((string) ($this->argument('texto') ?? $asunto));

        Mail::to($para)->send(new Aviso($asunto, $texto));

        $this->info("Aviso «{$asunto}» enviado a {$para} con el mailer «".config('mail.default').'».');

        return self::SUCCESS;
    }
}
