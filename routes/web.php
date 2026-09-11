<?php

use App\Http\Controllers\ClubPublicoController;
use App\Mail\NuevaSolicitud;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\AntiSpam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// ---------- Landing ----------

Route::view('/', 'public.landing')->name('landing');

// ---------- Páginas legales ----------
Route::view('/aviso-legal', 'public.legal.aviso-legal')->name('legal.aviso');
Route::view('/privacidad', 'public.legal.privacidad')->name('legal.privacidad');
Route::view('/cookies', 'public.legal.cookies')->name('legal.cookies');
Route::view('/condiciones', 'public.legal.condiciones')->name('legal.condiciones');

Route::post('/solicitud', function (Request $request) {
    $data = $request->validate([
        'club_nombre' => ['required', 'string', 'max:120'],
        'email' => ['required', 'email', 'max:120'],
        'mensaje' => ['nullable', 'string', 'max:1000'],
    ]);

    $data['email'] = mb_strtolower(trim($data['email']));

    // Trampas para bots: se les dice «recibido» igual (para que no insistan), pero
    // ni se guarda ni se avisa. Ver App\Services\AntiSpam.
    if ($motivo = AntiSpam::motivoParaDescartar($request, $data)) {
        Log::warning('Solicitud descartada como spam', ['motivo' => $motivo, 'ip' => $request->ip(), 'email' => $data['email']]);

        return back()->with('solicitud_ok', true);
    }

    $solicitud = Solicitud::create($data);

    // El aviso por email nunca debe tumbar el formulario si el correo falla.
    if ($para = config('plica.notificaciones_email')) {
        rescue(fn () => Mail::to($para)->send(new NuevaSolicitud($solicitud)));
    }

    return back()->with('solicitud_ok', true);
})->middleware('throttle:5,10')->name('solicitud.store');

// El WhatsApp de Plica no va en el HTML (los bots rastrean números): se entra
// por aquí y se redirige al pulsar. robots.txt lo excluye.
Route::get('/whatsapp', function () {
    abort_unless(filled(config('plica.whatsapp')), 404);

    return redirect()->away('https://wa.me/'.config('plica.whatsapp').'?text='.rawurlencode('Hola, soy de un club de pesca y quiero probar Plica.'));
})->name('whatsapp');

// ---------- Páginas públicas del club ----------
// La portada respeta «perfil público»; sección y manga son públicas SIEMPRE
// (se comparten por WhatsApp con gente de fuera del club).

Route::get('/c/{club:slug}', [ClubPublicoController::class, 'club'])->name('club.publico');
Route::get('/c/{club:slug}/manga/{manga}', [ClubPublicoController::class, 'manga'])->name('club.manga');
Route::post('/c/{club:slug}/manga/{manga}/asistire', [ClubPublicoController::class, 'asistire'])
    ->middleware('throttle:30,1')
    ->name('club.manga.asistire');
Route::get('/c/{club:slug}/{seccion}', [ClubPublicoController::class, 'seccion'])->name('club.seccion');

// ---------- Enlaces de acceso (un solo uso, por WhatsApp) ----------

Route::get('/acceso/{token}', function (string $token) {
    $socio = Socio::where('invite_token', $token)->where('activo', true)->first();

    if (! $socio) {
        return view('public.acceso-invalido');
    }

    return $socio->user_id
        ? view('public.acceso-restablecer', ['socio' => $socio, 'token' => $token])
        : view('public.acceso-crear', ['socio' => $socio, 'token' => $token]);
})->name('acceso.show');

Route::post('/acceso/{token}', function (Request $request, string $token) {
    $socio = Socio::where('invite_token', $token)->where('activo', true)->first();

    if (! $socio) {
        // Token ya consumido (p. ej. doble envío): página amable, no un 404.
        return redirect()->route('acceso.show', $token);
    }

    if ($socio->user_id) {
        // Restablecer contraseña de una cuenta existente.
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $socio->user;
        $user->update(['password' => Hash::make($data['password'])]);
    } else {
        // Crear cuenta nueva vinculada al socio.
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'club_id' => $socio->club_id,
            'role' => User::ROLE_SOCIO,
        ]);
        $user->forceFill(['password_cambiada_at' => now()])->save();

        $socio->user_id = $user->id;
        $socio->email = $socio->email ?: $data['email'];
    }

    // El enlace muere al usarse.
    $socio->invite_token = null;
    $socio->save();

    Auth::login($user, remember: true);
    $request->session()->regenerate();

    return redirect('/app');
})->middleware('throttle:10,1')->name('acceso.claim');
