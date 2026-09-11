<?php

namespace App\Providers;

use App\Http\Responses\LoginPorRol;
use Filament\Actions\CreateAction;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Un solo login: tras entrar, cada uno va a su panel según su rol.
        $this->app->bind(LoginResponse::class, LoginPorRol::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Un solo botón de crear en todos los modales: fuera «Crear y crear otro».
        CreateAction::configureUsing(
            fn (CreateAction $action) => $action->createAnother(false),
        );
    }
}
