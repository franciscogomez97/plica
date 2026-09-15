<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Socio;
use App\Services\Compartir;
use App\Services\Podio;
use App\Services\Scoring;
use App\Support\RankingDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        return view('public.seccion', RankingDeSeccion::datos($club, $seccion));
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

    /**
     * La tarjeta del podio de una manga (imagen para compartir y vista previa
     * del enlace). En una manga de club con varias secciones, `?seccion=slug`
     * elige cuál; sin ella, la primera. `?v=` es solo para que WhatsApp no
     * cachee una versión vieja: la imagen que se sirve es siempre la actual.
     */
    public function podioManga(Club $club, Manga $manga, Request $request): BinaryFileResponse
    {
        abort_unless($manga->temporada->club_id === $club->id, 404);

        $grupos = Scoring::clasificacionManga($manga);
        abort_if($grupos->isEmpty(), 404);

        $slug = $request->query('seccion');
        $grupo = $slug ? $grupos->first(fn (object $g) => $g->seccion?->slug === $slug) : $grupos->first();
        abort_if($grupo === null, 404);

        return $this->imagen(Podio::deManga($manga, $grupo), Str::slug($manga->nombre.' '.$grupo->nombre).'.jpg');
    }

    /** La tarjeta del ranking de una sección en la temporada activa. */
    public function podioSeccion(Club $club, string $seccion): BinaryFileResponse
    {
        $seccion = $club->seccions()->where('slug', $seccion)->firstOrFail();
        $temporada = $club->temporadaActiva();
        abort_if($temporada === null, 404);

        $grupo = Scoring::rankingTemporada($temporada)->firstWhere('seccionId', $seccion->id);
        abort_if($grupo === null, 404);

        return $this->imagen(Podio::deRanking($temporada, $seccion, $grupo), Str::slug('ranking '.$seccion->nombre).'.jpg');
    }

    private function imagen(string $ruta, string $nombre): BinaryFileResponse
    {
        return response()->file($ruta, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'Cache-Control' => 'public, max-age=300',
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
