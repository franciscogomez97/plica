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
                'texto' => 'Entraste con una contraseña generada por nosotros. Por seguridad, cámbiala por una tuya: arriba a la derecha, en el menú con tu nombre → «Perfil».',
                'boton' => 'Cambiar mi contraseña',
                'url' => url('/admin/profile'),
            ],
            [
                'hecho' => $temporada !== null,
                'informativo' => true, // no pide acción: su explicación se muestra siempre
                'titulo' => 'Qué es una temporada (la tuya ya está creada)',
                'texto' => 'Una temporada es el campeonato de un año: agrupa todas sus mangas y suma su ranking. Te hemos dejado creada y activa la «'.($temporada?->nombre ?? 'Temporada').'», así que aquí no tienes que hacer nada — solo saber que existe. Cuando acabe el año, crearás la siguiente desde «Temporadas».',
                'boton' => null,
                'url' => TemporadaResource::getUrl(),
            ],
            [
                'hecho' => $club !== null && $club->seccions()->exists(),
                'titulo' => 'Crea tus secciones',
                'texto' => 'Cada modalidad del club es una sección con sus propias reglas: «Bass orilla» clasificando por peso, «Lucio pato» por medida (captura y suelta), etc. Cada sección tiene su clasificación en cada manga y su propio ranking de temporada, con sus puntos por participar y sus descartes si los usáis.',
                'boton' => 'Crear mis secciones',
                'url' => SeccionResource::getUrl('create'),
            ],
            [
                'hecho' => $club !== null && $club->socios()->exists(),
                'titulo' => 'Da de alta a tus socios',
                'texto' => 'Solo necesitas su nombre — el email es opcional. Más adelante, desde la ficha de cada socio podrás mandarle por WhatsApp su enlace para que entre y vea los rankings.',
                'boton' => 'Añadir socios',
                'url' => SocioResource::getUrl('create'),
            ],
            [
                'hecho' => $temporada !== null && $temporada->mangas()->exists(),
                'titulo' => 'Crea tu primera manga',
                'texto' => 'La manga es cada jornada de competición. Ponle nombre, fecha y lugar — la fecha puede ser pasada si ya la pescasteis: podrás meter los resultados después. El día que toque gestionarla, este Inicio te avisará solo.',
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
