<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * Un solo login para todos: al entrar, cada uno va a su panel según su rol
 * (admin → panel del club, socio → su panel). Si venía de un enlace concreto,
 * se respeta ese destino.
 */
class LoginPorRol implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = Filament::auth()->user();
        $panel = $user instanceof User && $user->isAdmin() ? 'admin' : 'app';

        return redirect()->intended(Filament::getPanel($panel)->getUrl());
    }
}
