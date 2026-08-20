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
})->name('solicitud.store');

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

// ---------- Invitaciones (link por WhatsApp) ----------

Route::get('/invitacion/{token}', function (string $token) {
    $socio = Socio::where('invite_token', $token)->firstOrFail();

    if ($socio->user_id !== null) {
        return view('public.invitacion-usada', ['socio' => $socio]);
    }

    return view('public.invitacion', ['socio' => $socio, 'token' => $token]);
})->name('invitacion.show');

Route::post('/invitacion/{token}', function (Request $request, string $token) {
    $socio = Socio::where('invite_token', $token)->whereNull('user_id')->firstOrFail();

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

    $socio->update([
        'user_id' => $user->id,
        'email' => $socio->email ?: $data['email'],
    ]);

    Auth::login($user);

    return redirect('/app');
})->name('invitacion.claim');
