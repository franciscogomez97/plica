<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Socio;
use App\Services\Compartir;
use App\Services\Scoring;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Páginas públicas del club. La portada respeta el interruptor «perfil
 * público»; las clasificaciones de sección y de manga son públicas SIEMPRE:
 * se comparten por WhatsApp y cualquiera con el enlace debe poder verlas,
 * sea o no del club.
 */
class ClubPublicoController extends Controller
{
    public function club(Club $club): View
    {
        abort_unless($club->perfil_publico, 404);

        $temporada = $club->temporadaActiva();

        $proximas = $temporada
            ?->mangas()
            ->with('seccion')
            ->where('estado', Manga::ESTADO_PROGRAMADA)
            ->orderBy('fecha')
            ->get() ?? collect();

        $ultimaManga = $temporada
            ?->mangas()
            ->where('estado', Manga::ESTADO_CELEBRADA)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        return view('public.club', [
            'club' => $club,
            'temporada' => $temporada,
            'ranking' => $temporada ? Scoring::rankingTemporada($temporada) : collect(),
            'secciones' => $club->seccions()->get()->keyBy('id'),
            'proximas' => $proximas,
            'ultimaManga' => $ultimaManga,
            'clasifUltima' => $ultimaManga ? Scoring::clasificacionManga($ultimaManga) : collect(),
        ]);
    }

    public function seccion(Club $club, string $seccion): View
    {
        $seccion = $club->seccions()->where('slug', $seccion)->firstOrFail();
        $temporada = $club->temporadaActiva();

        $cuadro = $temporada ? Scoring::cuadroSeccion($temporada, $seccion) : null;
        $grupo = $temporada
            ? Scoring::rankingTemporada($temporada)->firstWhere('seccionId', $seccion->id)
            : null;

        $ultimaManga = $cuadro?->mangas->last();
        $clasifUltima = $ultimaManga
            ? Scoring::clasificacionManga($ultimaManga)->first(fn (object $g) => $g->seccion?->id === $seccion->id)
            : null;

        return view('public.seccion', [
            'club' => $club,
            'seccion' => $seccion,
            'temporada' => $temporada,
            'grupo' => $grupo,
            'cuadro' => $cuadro,
            'ultimaManga' => $ultimaManga,
            'clasifUltima' => $clasifUltima,
            'url' => $seccion->urlPublica(),
            'texto' => $grupo && $temporada ? Compartir::textoRanking($club, $temporada, $grupo) : null,
        ]);
    }

    public function manga(Club $club, Manga $manga): View
    {
        abort_unless($manga->temporada->club_id === $club->id, 404);

        $grupos = Scoring::clasificacionManga($manga);
        $socio = $this->socioDelClub($club);

        return view('public.manga', [
            'club' => $club,
            'manga' => $manga,
            'grupos' => $grupos,
            'url' => $manga->urlPublica(),
            'texto' => Compartir::textoManga($manga, $grupos),
            // Convocatoria: quién ha dicho que irá, y si quien mira puede decirlo.
            'confirmados' => $manga->confirmacions()->with('socio')->get()->map(fn ($c) => $c->socio->nombre)->sort()->values(),
            'socio' => $socio,
            'voy' => $manga->confirmadoPor($socio),
        ]);
    }

    /** «Asistiré» desde el enlace público: solo socios activos del club con cuenta. */
    public function asistire(Club $club, Manga $manga): RedirectResponse
    {
        abort_unless($manga->temporada->club_id === $club->id, 404);
        abort_unless($manga->estado === Manga::ESTADO_PROGRAMADA, 404);

        if (! auth()->check()) {
            return redirect()->route('filament.app.auth.login');
        }

        $socio = $this->socioDelClub($club);
        abort_unless($socio !== null, 403);

        $voy = $manga->alternarConfirmacion($socio);

        return redirect()
            ->to($manga->urlPublica())
            ->with('asistencia', $voy ? '¡Apuntado! El club ya sabe que irás.' : 'Confirmación retirada.');
    }

    /** El socio activo de este club que está mirando la página, si lo hay. */
    private function socioDelClub(Club $club): ?Socio
    {
        $socio = auth()->user()?->socio;

        return $socio !== null && $socio->club_id === $club->id && $socio->activo ? $socio : null;
    }
}
