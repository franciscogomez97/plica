<?php

namespace App\Filament\Resources\Socios\Tables;

use App\Models\Socio;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SociosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('participacions'))
            ->columns([
                // Una sola línea por socio; el email, donde cabe.
                Split::make([
                    TextColumn::make('nombre')
                        ->weight(FontWeight::SemiBold)
                        ->searchable(),
                    TextColumn::make('email')
                        ->placeholder('— sin email —')
                        ->color('gray')
                        ->searchable()
                        ->grow(false)
                        ->visibleFrom('md'),
                    TextColumn::make('insignias')
                        ->badge()
                        ->state(fn (Socio $record): array => array_values(array_filter([
                            $record->activo ? null : 'De baja',
                            $record->user_id ? 'Con cuenta' : 'Sin cuenta',
                        ])))
                        ->color(fn (string $state): string => match ($state) {
                            'De baja' => 'warning',
                            'Con cuenta' => 'success',
                            default => 'gray',
                        })
                        ->tooltip('Con cuenta: puede entrar y ver los rankings')
                        ->grow(false),
                ]),
            ])
            ->defaultSort('nombre')
            // Tocar la fila = editar la ficha del socio.
            ->recordUrl(fn (Socio $record): string => \App\Filament\Resources\Socios\SocioResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                Action::make('acceso')
                    ->label('Acceso')
                    ->icon('heroicon-o-link')
                    ->modalHeading('Enlace de acceso (un solo uso)')
                    ->modalDescription(fn (Socio $record): string => $record->user_id === null
                        ? 'Envíaselo por WhatsApp: al abrirlo creará su cuenta y verá los rankings.'
                        : 'Envíaselo por WhatsApp: al abrirlo elegirá una contraseña nueva (por si la ha olvidado).')
                    ->modalContent(fn (Socio $record) => view('filament.invite-link', ['url' => $record->accessUrl(), 'socio' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Editar'),
                ActionGroup::make([
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
                    DeleteAction::make()
                        // Un socio con historial no se borra: se da de baja.
                        ->visible(fn (Socio $record): bool => $record->participacions_count === 0),
                    Action::make('protegido')
                        ->label('Borrar')
                        ->icon('heroicon-o-trash')
                        ->color('gray')
                        ->visible(fn (Socio $record): bool => $record->participacions_count > 0)
                        ->modalHeading(fn (Socio $record): string => "{$record->nombre} no se puede borrar")
                        ->modalDescription(fn (Socio $record): string => 'Tiene '
                            .($record->participacions_count === 1 ? '1 pesaje' : "{$record->participacions_count} pesajes")
                            .' en el historial del club. Si ya no es socio, dale de baja: deja de aparecer en asistencias y listados, pero sus clasificaciones se conservan.')
                        ->modalSubmitActionLabel('Dar de baja')
                        ->modalCancelActionLabel('Cancelar')
                        ->action(fn (Socio $record) => $record->update(['activo' => false])),
                ]),
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
