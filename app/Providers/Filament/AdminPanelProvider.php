<?php

namespace App\Providers\Filament;

use App\Filament\Auth\LoginRedirigido;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\MiClub;
use App\Filament\Pages\Ranking;
use App\Filament\Pages\RankingSeccion;
use App\Filament\Resources\Mangas\MangaResource;
use App\Filament\Resources\Mangas\Pages\ClasificacionManga;
use App\Filament\Resources\Mangas\Pages\CreateManga;
use App\Filament\Resources\Mangas\Pages\EditManga;
use App\Filament\Resources\Mangas\Pages\PesajeManga;
use App\Http\Middleware\AutenticarPanelAdmin;
use App\Support\Marca;
use Filament\Auth\Pages\EditProfile;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Vite;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Flecha «Atrás» de las páginas interiores: como en una app, y
        // jerárquica (ver urlAtras), nunca el historial del navegador.
        $atras = function (): string {
            $url = static::urlAtras(Livewire::current());

            return $url === null
                ? ''
                : view('filament.partials.boton-atras', ['url' => $url])->render();
        };

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Sin login propio: /admin/login manda al login único de /app/login.
            ->login(LoginRedirigido::class)
            ->profile()
            ->darkMode(false) // siempre en claro: el oscuro del sistema no convence y confunde entre pantallas
            ->brandName('Plica · Panel del club')
            // Con sesión, la marca es el club: su logo y su nombre.
            ->brandLogo(fn () => auth()->user()?->club?->marca() ?? Marca::plica())
            ->brandLogoHeight('2.25rem')
            ->favicon(asset(Marca::FAVICON))
            ->colors([
                // Emerald-600 sobre blanco da 3,77:1 y AA pide 4,5:1: los botones y enlaces
                // (tono 600 en Filament) usan el 700 (5,5:1), y el 500 el 600. Casi no se nota.
                'primary' => array_replace(Color::Emerald, [500 => Color::Emerald[600], 600 => Color::Emerald[700]]),
            ])
            // Navegación sin recarga completa: sensación de app.
            ->spa()
            // Theme-color del móvil + acciones de fila (lápiz/papelera) en la
            // MISMA línea que el contenido, también en pantallas pequeñas.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Marca::iconos().Marca::estilosPanel().view('partials.copiar')->render()
                    // Utilidades de la web pública: el ranking del admin pinta los mismos parciales que la web.
                    .app(Vite::class)->__invoke(['resources/css/publico.css'])->toHtml()
                    .'<style>'
                    .'.fi-ta-record .fi-ta-record-content-ctn { flex-direction: row; align-items: center; }'
                    .'.fi-ta-record .fi-ta-record-content-ctn > div:first-child { flex: 1 1 0%; min-width: 0; }'
                    .'.fi-ta-record .fi-ta-actions.fi-wrapped { flex: 0 0 auto; width: auto; flex-wrap: nowrap; padding-inline: .25rem .9rem; }'
                    // La frase «Así puntúa esta sección» se queda pegada arriba mientras se tocan las opciones.
                    .'.plica-resumen-fijo { position: sticky; top: 4.5rem; z-index: 5; padding: .6rem .8rem; border-radius: .6rem; background: #ecfdf5; border: 1px solid rgba(5, 150, 105, .35); }'
                    .'</style>',
            )
            ->renderHook(PanelsRenderHook::PAGE_START, $atras)
            // «Lleva Plica en el móvil»: en el Inicio del admin, justo debajo del «Hola» y antes de los widgets.
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_WIDGETS_BEFORE,
                fn (): string => view('filament.partials.instalar-app')->render(),
                scopes: Inicio::class,
            )
            // El perfil usa el layout «simple» de Filament, que tiene su propio hook.
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_START, $atras)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Inicio::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AutenticarPanelAdmin::class,
            ]);
    }

    /**
     * A dónde lleva «Atrás» desde cada página interior: a su «padre» en la
     * jerarquía (Mangas → pesaje de la manga → datos / clasificación), no a
     * la página anterior del historial. Así un formulario recién enviado no
     * vuelve a aparecer relleno y no se crea dos veces lo mismo.
     */
    private static function urlAtras(?object $page): ?string
    {
        return match (true) {
            $page instanceof PesajeManga, $page instanceof CreateManga => MangaResource::getUrl(),
            $page instanceof EditManga, $page instanceof ClasificacionManga => MangaResource::getUrl('pesaje', ['record' => $page->getRecord()]),
            $page instanceof CreateRecord, $page instanceof EditRecord => $page::getResource()::getUrl(),
            $page instanceof EditProfile => Inicio::getUrl(),
            $page instanceof RankingSeccion => Ranking::urlDeSeccion($page->getSeccion()),
            $page instanceof MiClub => Inicio::getUrl(),
            default => null,
        };
    }
}
