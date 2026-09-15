<?php

namespace App\Filament\Resources\Mangas\Tables;

use App\Filament\Resources\Mangas\MangaResource;
use App\Models\Manga;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MangasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('seccion')->withCount(['participacions', 'confirmacions']))
            ->columns([
                // Una sola línea por manga; el detalle aparece según cabe.
                Split::make([
                    Stack::make([
                        TextColumn::make('nombre')
                            ->weight(FontWeight::SemiBold)
                            ->searchable(),
                        // En el móvil no cabe la insignia de sección al lado: va debajo del nombre
                        // (tres «3ª Manga» iguales no se distinguen sin ella).
                        TextColumn::make('seccion_movil')
                            ->state(fn (Manga $record): ?string => $record->seccion?->nombre)
                            ->color('gray')
                            ->size('xs')
                            ->hiddenFrom('sm'),
                    ]),
                    TextColumn::make('seccion.nombre')
                        ->badge()
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('sm'),
                    TextColumn::make('lugar')
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('md'),
                    TextColumn::make('temporada.nombre')
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('lg'),
                    TextColumn::make('participacions_count')
                        // Antes de pasar lista, lo útil es cuántos han dicho que irán.
                        ->state(fn (Manga $record): string => $record->participacions_count === 0 && $record->confirmacions_count > 0
                            ? ($record->confirmacions_count === 1 ? '1 confirmado' : "{$record->confirmacions_count} confirmados")
                            : ($record->participacions_count === 1 ? '1 participante' : "{$record->participacions_count} participantes"))
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('lg'),
                    TextColumn::make('fecha')
                        ->formatStateUsing(fn (Manga $record): string => $record->fecha->format('d/m/Y').($record->horarioCorto() ? ' · '.$record->horarioCorto() : ''))
                        ->color('gray')
                        ->grow(false),
                    TextColumn::make('estado')
                        ->badge()
                        ->state(fn (Manga $record): string => $record->pendienteDeGestion() ? 'pendiente' : $record->estado)
                        ->color(fn (string $state): string => match ($state) {
                            Manga::ESTADO_CELEBRADA => 'success',
                            'pendiente' => 'danger',
                            default => 'warning',
                        })
                        ->formatStateUsing(fn (string $state): string => $state === 'pendiente' ? 'Por gestionar' : ucfirst($state))
                        ->grow(false),
                ]),
            ])
            ->defaultSort('fecha', 'desc')
            ->paginated(false)
            // Tocar la fila = pesaje rápido, que es lo que se hace con una manga.
            ->recordUrl(fn (Manga $record): string => MangaResource::getUrl('pesaje', ['record' => $record]))
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Editar datos'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Borrar')
                    // Con pesajes dentro no se borra: primero habría que vaciarla.
                    ->visible(fn (Manga $record): bool => $record->participacions_count === 0),
                Action::make('protegida')
                    ->iconButton()
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->tooltip('No se puede borrar: tiene participaciones')
                    ->visible(fn (Manga $record): bool => $record->participacions_count > 0)
                    ->modalHeading(fn (Manga $record): string => "«{$record->nombre}» no se puede borrar")
                    ->modalDescription(fn (Manga $record): string => 'Tiene '
                        .($record->participacions_count === 1 ? '1 participante' : "{$record->participacions_count} participantes")
                        .' apuntados. Para borrarla, primero desmárcalos a todos en «Marcar asistencia» dentro del pesaje; si alguno tiene capturas, habrá que vaciarlas antes.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Entendido'),
            ]);
    }
}
