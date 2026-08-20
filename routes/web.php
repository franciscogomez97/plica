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

    Solicitud::create($data);

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
    $socio = Socio::where('invite_token', $token)->first();

    if (! $socio) {
        return view('public.acceso-invalido');
    }

    return $socio->user_id
        ? view('public.acceso-restablecer', ['socio' => $socio, 'token' => $token])
        : view('public.acceso-crear', ['socio' => $socio, 'token' => $token]);
})->name('acceso.show');

Route::post('/acceso/{token}', function (Request $request, string $token) {
    $socio = Socio::where('invite_token', $token)->firstOrFail();

    if ($socio->user_id) {
        // Restablecer contraseña de una cuenta existente.
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $socio->user;
        $user->update(['password' => Hash::make($data['password'])]);
    } else {
        // Crear cuenta nueva vinculada al socio.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'club_id' => $socio->club_id,
            'role' => User::ROLE_SOCIO,
        ]);

        $socio->user_id = $user->id;
        $socio->email = $socio->email ?: $data['email'];
    }

    // El enlace muere al usarse.
    $socio->invite_token = null;
    $socio->save();

    Auth::login($user, remember: true);

    return redirect('/app');
})->middleware('throttle:10,1')->name('acceso.claim');
