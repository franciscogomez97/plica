<?php

namespace App\Services;

use App\Models\Solicitud;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Trampas para bots en el formulario de solicitud de la landing, sin captcha
 * ni molestias para las personas:
 *  - Un campo oculto («web») que una persona no ve ni rellena; un bot que
 *    rellena todo, sí.
 *  - Un sello de tiempo cifrado al pintar el formulario: si se envía en menos
 *    de cuatro segundos, no lo ha escrito nadie.
 *  - Enlaces en el mensaje: un presidente cuenta cómo lleva las plicas, no
 *    pega URLs.
 *  - El mismo email dos veces en 24 h: la primera ya está apuntada.
 * Al bot se le contesta «recibido» igual, para que no insista.
 */
class AntiSpam
{
    public const CAMPO_TRAMPA = 'web';

    public const CAMPO_SELLO = 'sello';

    public const SEGUNDOS_MINIMOS = 4;

    /** El valor del sello para el formulario: el momento en que se pintó, cifrado. */
    public static function sello(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    /** Null si parece una persona; si no, el motivo (para el log). */
    public static function motivoParaDescartar(Request $request, array $data): ?string
    {
        if (filled($request->input(self::CAMPO_TRAMPA))) {
            return 'campo trampa relleno';
        }

        try {
            $pintado = (int) Crypt::decryptString((string) $request->input(self::CAMPO_SELLO));
        } catch (DecryptException) {
            return 'sin sello de tiempo';
        }

        if (now()->timestamp - $pintado < self::SEGUNDOS_MINIMOS) {
            return 'enviado en menos de '.self::SEGUNDOS_MINIMOS.' segundos';
        }

        if (preg_match('~https?://|www\.~i', (string) ($data['mensaje'] ?? ''))) {
            return 'enlaces en el mensaje';
        }

        if (Solicitud::where('email', $data['email'])->where('created_at', '>=', now()->subDay())->exists()) {
            return 'mismo email en las últimas 24 h';
        }

        return null;
    }
}
