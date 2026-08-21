<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use SensitiveParameter;

/**
 * Login tolerante con cómo escribe la gente de verdad:
 * "  Paco@Gmail.com " encuentra la cuenta "paco@gmail.com".
 */
class Login extends BaseLogin
{
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
