<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use SensitiveParameter;

/**
 * El único login de Plica: socios y administradores entran por la misma
 * puerta y cada uno acaba en su panel (ver LoginPorRol). Tolerante con cómo
 * escribe la gente de verdad: "  Paco@Gmail.com " encuentra "paco@gmail.com".
 */
class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable
    {
        return 'Entrar en Plica';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Socios y administradores del club, por la misma puerta.';
    }

    public function authenticate(): ?LoginResponse
    {
        // Normalizar ANTES de validar: los espacios harían fallar la regla `email`.
        if (isset($this->data['email']) && is_string($this->data['email'])) {
            $this->data['email'] = mb_strtolower(trim($this->data['email']));
        }

        return parent::authenticate();
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'email' => mb_strtolower(trim($data['email'])),
            'password' => $data['password'],
        ];
    }
}
