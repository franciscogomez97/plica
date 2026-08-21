<?php

namespace App\Filament\Resources\Mangas\RelationManagers;

use App\Models\Seccion;
use App\Models\Socio;
use App\Services\Scoring;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParticipacionsRelationManager extends RelationManager
{
    protected static string $relationship = 'participacions';

    protected static ?string $title = 'Participaciones y pesajes';

    protected static ?string $modelLabel = 'participación';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('socio_id')
                    ->label('Socio')
                    ->options(fn (): array => Socio::query()
                        ->where('club_id', auth()->user()->club_id)
                        ->where('activo', true)
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all())
                    ->disableOptionWhen(fn (string $value, string $operation): bool => $operation === 'create'
                        && $this->getOwnerRecord()->participacions()->where('socio_id', $value)->exists())
                    ->searchable()
                    ->required(),
                Select::make('seccion_id')
                    ->label('Sección')
                    ->options(fn (): array => Seccion::query()
                        ->where('club_id', auth()->user()->club_id)
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all())
                    ->nullable(),
                Toggle::make('plica')
                    ->label('Entregó plica')
                    ->default(true),
                Repeater::make('capturas')
                    ->relationship()
                    ->label('Capturas')
                    ->schema([
                        TextInput::make('piezas')
                            ->label('Piezas')
                            ->numeric()
                            ->minValue(0)
                            ->default(1)
                            ->required(),
                        TextInput::make('peso_gramos')
                            ->label('Peso')
                            ->suffix('g')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('medida_mm')
                            ->label('Medida')
                            ->suffix('cm')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.5)
                            ->formatStateUsing(fn ($state) => filled($state) ? $state / 10 : null)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) round(((float) $state) * 10) : null),
                        TextInput::make('nota')
                            ->label('Nota'),
                    ])
                    ->columns(['default' => 1, 'sm' => 4])
                    ->defaultItems(1)
                    ->addActionLabel('Añadir línea de captura')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['socio', 'seccion', 'capturas']))
            ->columns([
                // Una sola línea por pesaje; el detalle, donde cabe.
                Split::make([
                    TextColumn::make('socio.nombre')
                        ->weight(FontWeight::SemiBold)
                        ->searchable(),
                    TextColumn::make('detalle')
                        ->state(function ($record): string {
                            $piezas = $record->piezasTotal();

                            return ($record->seccion?->nombre ?? 'Sin sección')
                                .' · '.($piezas === 1 ? '1 pieza' : "{$piezas} piezas");
                        })
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('sm'),
                    TextColumn::make('plica')
                        ->badge()
                        ->state(fn ($record): ?string => $record->plica ? null : 'Sin plica')
                        ->color('warning')
                        ->grow(false),
                    TextColumn::make('valor')
                        ->state(fn ($record): string => Scoring::valorPrincipal(
                            $record->seccion?->criterio ?? Seccion::CRITERIO_PESO,
                            (object) [
                                'piezas' => $record->piezasTotal(),
                                'peso' => $record->pesoTotal(),
                                'medida' => $record->medidaTotal(),
                            ],
                        ))
                        ->weight(FontWeight::Bold)
                        ->grow(false),
                ]),
            ])
            // Tocar la fila = editar el pesaje.
            ->recordAction('edit')
            ->headerActions([
                Action::make('asistencia')
                    ->label('Marcar asistencia')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->modalHeading('¿Quién ha participado en esta manga?')
                    ->modalDescription('Marca a los que han venido. Los desmarcados se quitan, salvo que ya tengan capturas.')
                    ->modalSubmitActionLabel('Guardar asistencia')
                    ->schema([
                        CheckboxList::make('socios')
                            ->label('Socios')
                            ->options(fn (): array => Socio::query()
                                ->where('club_id', auth()->user()->club_id)
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                                ->all())
                            ->default(fn (): array => $this->getOwnerRecord()
                                ->participacions()
                                ->pluck('socio_id')
                                ->map(fn ($id) => (string) $id)
                                ->all())
                            ->columns(['default' => 1, 'sm' => 2])
                            ->bulkToggleable(),
                        Select::make('seccion_id')
                            ->label('Sección para los recién marcados')
                            ->helperText('Solo para los recién marcados; luego se puede cambiar.')
                            ->options(fn (): array => Seccion::query()
                                ->where('club_id', auth()->user()->club_id)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                                ->all())
                            ->nullable(),
                    ])
                    ->action(function (array $data): void {
                        $resultado = $this->getOwnerRecord()->sincronizarAsistencia(
                            $data['socios'] ?? [],
                            $data['seccion_id'] ? (int) $data['seccion_id'] : null,
                        );

                        $notificacion = Notification::make()
                            ->title('Asistencia guardada')
                            ->body("{$resultado['creadas']} añadidos · {$resultado['eliminadas']} quitados")
                            ->success();

                        if ($resultado['bloqueadas'] !== []) {
                            $notificacion
                                ->warning()
                                ->body('No se quitaron (tienen capturas): '.implode(', ', $resultado['bloqueadas']));
                        }

                        $notificacion->send();
                    }),
                CreateAction::make()
                    ->label('Añadir participación'),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Editar pesaje'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Quitar'),
            ]);
    }
}
