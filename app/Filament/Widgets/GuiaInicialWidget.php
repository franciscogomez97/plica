<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Mangas\MangaResource;
use App\Filament\Resources\Seccions\SeccionResource;
use App\Filament\Resources\Socios\SocioResource;
use App\Filament\Resources\Temporadas\TemporadaResource;
use App\Models\Manga;
use Filament\Widgets\Widget;

/**
 * Guía de primeros pasos para admins nuevos. Cada paso se tacha solo
 * cuando el sistema detecta que está hecho; al completarlos (o al
 * omitirla) desaparece y el Inicio pasa a modo operativo.
 */
class GuiaInicialWidget extends Widget
{
    protected string $view = 'filament.widgets.guia-inicial';

    protected static ?int $sort = -20;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->guia_completada_at === null;
    }

    /** @return array<int, array{hecho: bool, titulo: string, texto: string, boton: ?string, url: ?string}> */
    public function getPasos(): array
    {
        $user = auth()->user();
        $club = $user->club;
        $temporada = $club?->temporadaActiva();

        return [
            [
                'hecho' => $user->password_cambiada_at !== null,
                'titulo' => 'Pon tu propia contraseña',
                'texto' => 'Entraste con una contraseña generada. Ponte una tuya: menú de arriba a la derecha → «Perfil».',
                'boton' => 'Cambiar mi contraseña',
                'url' => url('/admin/profile'),
            ],
            [
                'hecho' => $temporada !== null,
                'informativo' => true, // no pide acción: su explicación se muestra siempre
                'titulo' => 'Tu temporada, explicada',
                'texto' => 'La temporada agrupa las mangas de un año y su ranking. La «'.($temporada?->nombre ?? 'Temporada').'» ya está creada y activa: no tienes que hacer nada.',
                'boton' => null,
                'url' => TemporadaResource::getUrl(),
            ],
            [
                'hecho' => $club !== null && $club->seccions()->exists(),
                'titulo' => 'Crea tus secciones',
                'texto' => 'Cada modalidad es una sección con sus reglas: «Bass orilla» por peso, «Lucio pato» por medida… Cada una tiene su clasificación y su ranking.',
                'boton' => 'Crear mis secciones',
                'url' => SeccionResource::getUrl('create'),
            ],
            [
                'hecho' => $club !== null && $club->socios()->exists(),
                'titulo' => 'Da de alta a tus socios',
                'texto' => 'Pega la lista de nombres tal cual la tengas (del WhatsApp o del papel), uno por línea, con «Añadir varios». Solo hace falta el nombre; el enlace para ver los rankings se lo mandas después por WhatsApp.',
                'boton' => 'Añadir socios',
                'url' => SocioResource::getUrl(),
            ],
            [
                'hecho' => $temporada !== null && $temporada->mangas()->exists(),
                'titulo' => 'Crea tu primera manga',
                'texto' => 'Cada jornada de competición es una manga. La fecha puede ser pasada: los resultados se meten después, y este Inicio te avisará cuando toque.',
                'boton' => 'Crear mi primera manga',
                'url' => MangaResource::getUrl('create'),
            ],
        ];
    }

    public function getPasoActual(): ?int
    {
        foreach ($this->getPasos() as $i => $paso) {
            if (! $paso['hecho']) {
                return $i;
            }
        }

        return null; // todo hecho
    }

    public function omitir(): void
    {
        auth()->user()->forceFill(['guia_completada_at' => now()])->save();
    }
}
