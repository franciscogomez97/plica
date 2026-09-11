<?php

namespace App\Filament\Resources\Socios\Schemas;

use App\Models\Socio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * La ficha de un socio pide lo justo: nombre, teléfono (para mandarle el acceso
 * por WhatsApp) y email. Nada más. La baja/alta solo aparece al editar.
 */
class SocioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(120),
                TextInput::make('telefono')
                    ->label('Teléfono (móvil, para WhatsApp)')
                    ->tel()
                    // La regex de Filament rechaza espacios al principio; lo que valida de verdad es telefonoWhatsApp().
                    ->telRegex('/^[\s+\d().\-]+$/')
                    ->placeholder('600 11 22 33')
                    ->maxLength(40)
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim(preg_replace('/\s+/u', ' ', $state) ?? $state) : null)
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (filled($value) && Socio::telefonoWhatsApp($value) === null) {
                            $fail('No parece un teléfono: pon el móvil con sus 9 cifras (o con prefijo, +34…).');
                        }
                    })
                    ->nullable(),
                TextInput::make('email')
                    ->label('Email (opcional)')
                    ->email()
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtolower(trim($state)) : null)
                    ->nullable(),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true)
                    ->hiddenOn('create'),
            ]);
    }
}
