<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Un solo botón de crear en todos los modales: fuera «Crear y crear otro».
        \Filament\Actions\CreateAction::configureUsing(
            fn (\Filament\Actions\CreateAction $action) => $action->createAnother(false),
        );
    }
}
