<?php

namespace App\Http\Middleware;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Igual que el Authenticate de Filament, pero un socio autenticado que
 * entra en /admin por error va a su panel en vez de a un error 403.
 */
class AutenticarPanelAdmin extends FilamentAuthenticate
{
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if ($guard->check()) {
            $user = $guard->user();

            if ($user instanceof User && ! $user->isAdmin()) {
                throw new HttpResponseException(redirect('/app'));
            }
        }

        parent::authenticate($request, $guards);
    }
}
