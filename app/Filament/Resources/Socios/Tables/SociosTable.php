<?php

namespace App\Filament\Resources\Socios\Tables;

use App\Filament\Resources\Socios\Pages\ListSocios;
use App\Filament\Resources\Socios\SocioResource;
use App\Models\Socio;
use App\Models\User;
use App\Services\FotoSocio;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class SociosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('seccions')->withCount([
                'participacions',
                // En equipos la plica es del barco: un socio sin pesajes propios puede tener historial por su equipo.
                'equipos as equipos_con_historial_count' => fn ($q) => $q->whereHas('participacions'),
            ]))
            ->columns([
                // En pantalla ancha, una línea por socio; en el móvil se apila (nombre,
                // teléfono, insignias) y así el botón «Acceso» no se sale por la derecha.
                Split::make([
                    // La foto solo se enseña aquí, de momento: ni en rankings ni en la web pública.
                    ImageColumn::make('foto')
                        ->label('')
                        ->disk(FotoSocio::DISCO)
                        ->visibility('public')
                        ->circular()
                        ->imageSize(40)
                        ->defaultImageUrl(asset('img/socio.svg'))
                        ->grow(false),
                    TextColumn::make('nombre')
                        ->weight(FontWeight::SemiBold)
                        ->searchable(),
                    TextColumn::make('licencia')
                        ->formatStateUsing(fn (string $state): string => "Lic. {$state}")
                        ->color('gray')
                        ->searchable()
                        ->grow(false)
                        ->visibleFrom('md'),
                    TextColumn::make('telefono')
                        ->placeholder('sin teléfono')
                        ->color('gray')
                        ->searchable()
                        ->grow(false),
                    TextColumn::make('email')
                        ->placeholder('— sin email —')
                        ->color('gray')
                        ->searchable()
                        ->grow(false)
                        ->visibleFrom('md'),
                    // En «Todos», de qué secciones es cada uno; en la pestaña de una sección sobra.
                    TextColumn::make('seccions.nombre')
                        ->badge()
                        ->color('info')
                        ->placeholder('sin sección')
                        ->visible(fn (ListSocios $livewire): bool => $livewire->seccionActiva() === null && $livewire->getCachedTabs() !== [])
                        ->grow(false)
                        ->visibleFrom('sm'),
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
                ])->from('md'),
            ])
            ->defaultSort('nombre')
            // Todos los socios de una vez: un club tiene decenas, no miles, y nadie busca «página 2» de su lista.
            ->paginated(false)
            // Tocar la fila = editar la ficha del socio.
            ->recordUrl(fn (Socio $record): string => SocioResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                Action::make('acceso')
                    ->label('Acceso')
                    ->icon('heroicon-o-link')
                    ->modalHeading(fn (Socio $record): string => "Dar acceso a {$record->nombre}")
                    ->modalDescription(fn (Socio $record): string => $record->user_id === null
                        ? 'Aún no tiene cuenta. Mándale su enlace y, al abrirlo, la crea.'
                        : 'Ya tiene cuenta. Este enlace le sirve para poner una contraseña nueva.')
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
                        // Un socio con historial (propio o de su equipo) no se borra: se da de baja.
                        ->visible(fn (Socio $record): bool => $record->participacions_count === 0 && $record->equipos_con_historial_count === 0),
                    Action::make('protegido')
                        ->label('Borrar')
                        ->icon('heroicon-o-trash')
                        ->color('gray')
                        ->visible(fn (Socio $record): bool => $record->participacions_count > 0 || $record->equipos_con_historial_count > 0)
                        ->modalHeading(fn (Socio $record): string => "{$record->nombre} no se puede borrar")
                        ->modalDescription(fn (Socio $record): string => 'Tiene '
                            .implode(' y ', array_filter([
                                $record->participacions_count > 0 ? ($record->participacions_count === 1 ? '1 pesaje' : "{$record->participacions_count} pesajes") : null,
                                $record->equipos_con_historial_count > 0 ? ($record->equipos_con_historial_count === 1 ? 'un equipo con pesajes' : "{$record->equipos_con_historial_count} equipos con pesajes") : null,
                            ]))
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
