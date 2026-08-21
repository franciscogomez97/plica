<?php

namespace App\Filament\Resources\Socios\Tables;

use App\Models\Socio;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SociosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('— sin email —')
                    ->searchable()
                    ->visibleFrom('sm'),
                IconColumn::make('user_id')
                    ->label('Cuenta')
                    ->boolean()
                    ->tooltip(fn (Socio $record): string => $record->user_id
                        ? 'Tiene cuenta y puede ver los rankings'
                        : 'Sin cuenta (el admin gestiona sus datos)'),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('nombre')
            ->recordActions([
                Action::make('acceso')
                    ->label('Enlace de acceso')
                    ->icon('heroicon-o-link')
                    ->modalHeading('Enlace de acceso (un solo uso)')
                    ->modalDescription(fn (Socio $record): string => $record->user_id === null
                        ? 'Envíaselo por WhatsApp: al abrirlo creará su cuenta y verá los rankings.'
                        : 'Envíaselo por WhatsApp: al abrirlo elegirá una contraseña nueva (por si la ha olvidado).')
                    ->modalContent(fn (Socio $record) => view('filament.invite-link', ['url' => $record->accessUrl(), 'socio' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
