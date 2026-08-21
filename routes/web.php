<?php

use App\Models\Club;
use App\Models\Manga;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\Scoring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// ---------- Landing ----------

Route::view('/', 'public.landing')->name('landing');

Route::post('/solicitud', function (Request $request) {
    $data = $request->validate([
        'club_nombre' => ['required', 'string', 'max:120'],
        'email' => ['required', 'email', 'max:120'],
        'mensaje' => ['nullable', 'string', 'max:1000'],
    ]);

    $data['email'] = mb_strtolower(trim($data['email']));

    $solicitud = Solicitud::create($data);

    // El aviso por email nunca debe tumbar el formulario si el correo falla.
    if ($para = config('plica.notificaciones_email')) {
        rescue(fn () => \Illuminate\Support\Facades\Mail::to($para)->send(new \App\Mail\NuevaSolicitud($solicitud)));
    }

    return back()->with('solicitud_ok', true);
})->middleware('throttle:10,1')->name('solicitud.store');

// ---------- Página pública del club ----------

Route::get('/c/{club:slug}', function (Club $club) {
    abort_unless($club->perfil_publico, 404);

    $temporada = $club->temporadaActiva();

    $proximas = $temporada
        ?->mangas()
        ->where('estado', Manga::ESTADO_PROGRAMADA)
        ->orderBy('fecha')
        ->get() ?? collect();

    $ultimaManga = $temporada
        ?->mangas()
        ->where('estado', Manga::ESTADO_CELEBRADA)
        ->orderByDesc('fecha')
        ->first();

    return view('public.club', [
        'club' => $club,
        'temporada' => $temporada,
        'ranking' => $temporada ? Scoring::rankingTemporada($temporada) : collect(),
        'proximas' => $proximas,
        'ultimaManga' => $ultimaManga,
        'clasifUltima' => $ultimaManga ? Scoring::clasificacionManga($ultimaManga) : collect(),
    ]);
})->name('club.publico');

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
