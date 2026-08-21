<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\Inicio;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('app')
            ->login(\App\Filament\Auth\Login::class)
            ->profile()
            ->brandName('Plica')
            ->favicon('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2224%22 fill=%22%23059669%22/><ellipse cx=%2242%22 cy=%2252%22 rx=%2225%22 ry=%2215%22 fill=%22white%22/><path d=%22M63 52l21-15v30z%22 fill=%22white%22/><circle cx=%2229%22 cy=%2248%22 r=%223.5%22 fill=%22%23059669%22/></svg>')
            ->colors([
                'primary' => Color::Emerald,
            ])
            // El panel del socio es una sola pantalla: sin menú lateral,
            // contenido estrecho y navegación sin recargas — sensación de app.
            ->navigation(false)
            ->maxContentWidth(\Filament\Support\Enums\Width::TwoExtraLarge)
            ->spa()
            // El navegador del móvil tiñe su barra del verde de Plica.
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => '<meta name="theme-color" content="#059669">',
            )
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
