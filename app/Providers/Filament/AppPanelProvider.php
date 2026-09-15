<?php

namespace App\Providers\Filament;

use App\Filament\App\Auth\Perfil;
use App\Filament\App\Pages\ClasificacionManga;
use App\Filament\App\Pages\Inicio;
use App\Filament\App\Pages\RankingSeccion;
use App\Filament\Auth\Login;
use App\Support\Marca;
use Filament\Auth\Pages\EditProfile;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $atras = function (): string {
            $pagina = Livewire::current();
            $interior = $pagina instanceof RankingSeccion
                || $pagina instanceof ClasificacionManga
                || $pagina instanceof EditProfile;

            return $interior
                ? view('filament.partials.boton-atras', ['url' => Inicio::getUrl()])->render()
                : '';
        };

        return $panel
            ->id('app')
            ->path('app')
            ->login(Login::class)
            ->profile(Perfil::class) // nombre, email, contraseña, y además foto y licencia federativa
            ->darkMode(false) // siempre en claro: el oscuro del sistema no convence y confunde entre pantallas
            ->brandName('Plica')
            // Con sesión, la marca es el club: su logo y su nombre.
            ->brandLogo(fn () => auth()->user()?->club?->marca() ?? Marca::plica())
            ->brandLogoHeight('2.25rem')
            ->favicon(asset(Marca::FAVICON))
            ->colors([
                // Emerald-600 sobre blanco da 3,77:1 y AA pide 4,5:1: los botones y enlaces
                // (tono 600 en Filament) usan el 700 (5,5:1), y el 500 el 600. Casi no se nota.
                'primary' => array_replace(Color::Emerald, [500 => Color::Emerald[600], 600 => Color::Emerald[700]]),
            ])
            // El panel del socio es una sola pantalla: sin menú lateral,
            // contenido estrecho y navegación sin recargas — sensación de app.
            ->navigation(false)
            ->maxContentWidth(Width::TwoExtraLarge)
            ->spa()
            // El navegador del móvil tiñe su barra del verde de Plica, y la app se
            // puede «añadir a pantalla de inicio» como una app de verdad.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Marca::iconos().Marca::estilosPanel(),
            )
            // Flecha «Atrás» en las páginas interiores del socio (ranking de una
            // sección, clasificación de una manga, perfil): siempre al Inicio.
            ->renderHook(PanelsRenderHook::PAGE_START, $atras)
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_START, $atras)
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->pages([
                Inicio::class,
            ])
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
                Authenticate::class,
            ]);
    }
}
