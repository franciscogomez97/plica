<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AutenticarPanelAdmin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Inicio;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Auth\Login::class)
            ->profile()
            ->brandName('Plica · Panel del club')
            ->favicon('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2224%22 fill=%22%23059669%22/><ellipse cx=%2242%22 cy=%2252%22 rx=%2225%22 ry=%2215%22 fill=%22white%22/><path d=%22M63 52l21-15v30z%22 fill=%22white%22/><circle cx=%2229%22 cy=%2248%22 r=%223.5%22 fill=%22%23059669%22/></svg>')
            ->colors([
                'primary' => Color::Emerald,
            ])
            // Navegación sin recarga completa: sensación de app.
            ->spa()
            // Theme-color del móvil + acciones de fila (lápiz/papelera) en la
            // MISMA línea que el contenido, también en pantallas pequeñas.
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => '<meta name="theme-color" content="#059669">'
                    .'<style>'
                    .'.fi-ta-record .fi-ta-record-content-ctn { flex-direction: row; align-items: center; }'
                    .'.fi-ta-record .fi-ta-record-content-ctn > div:first-child { flex: 1 1 0%; min-width: 0; }'
                    .'.fi-ta-record .fi-ta-actions.fi-wrapped { flex: 0 0 auto; width: auto; flex-wrap: nowrap; padding-inline: .25rem .9rem; }'
                    .'</style>',
            )
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
}
