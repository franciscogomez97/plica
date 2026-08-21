<?php

namespace App\Filament\Resources\Socios\Tables;

use App\Models\Socio;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Collection;
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
                Action::make('hacerAdmin')
                    ->label('Hacer admin')
                    ->icon('heroicon-o-key')
                    ->visible(fn (Socio $record): bool => $record->user !== null && ! $record->user->isAdmin())
                    ->requiresConfirmation()
                    ->modalHeading('¿Hacer admin del club?')
                    ->modalDescription(fn (Socio $record): string => "{$record->nombre} podrá gestionar mangas, pesajes y socios igual que tú.")
                    ->action(fn (Socio $record) => $record->user->update(['role' => User::ROLE_ADMIN])),
                Action::make('quitarAdmin')
                    ->label('Quitar admin')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->visible(fn (Socio $record): bool => $record->user !== null
                        && $record->user->isAdmin()
                        && $record->user_id !== auth()->id()) // nadie se quita a sí mismo
                    ->requiresConfirmation()
                    ->action(fn (Socio $record) => $record->user->update(['role' => User::ROLE_SOCIO])),
                EditAction::make(),
                DeleteAction::make()
                    // Un socio con historial no se borra: se da de baja.
                    ->visible(fn (Socio $record): bool => $record->participacions()->doesntExist()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('baja')
                        ->label('Dar de baja')
                        ->icon('heroicon-o-user-minus')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('Dejan de aparecer en asistencias y listados, pero su historial de pesajes se conserva.')
                        ->action(fn (Collection $records) => $records->each->update(['activo' => false]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('alta')
                        ->label('Dar de alta')
                        ->icon('heroicon-o-user-plus')
                        ->action(fn (Collection $records) => $records->each->update(['activo' => true]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
