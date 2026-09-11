<?php

namespace App\Filament\Auth;

use Filament\Facades\Filament;

/**
 * El panel del club no tiene login propio: /admin/login manda al login único
 * (/app/login). El destino al que iba el usuario se conserva en sesión, así
 * que tras entrar acaba donde quería.
 */
class LoginRedirigido extends Login
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            parent::mount();

            return;
        }

        $this->redirect(Filament::getPanel('app')->getLoginUrl());
    }
}
