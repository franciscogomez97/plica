<?php

namespace App\Filament\Resources\Mangas\RelationManagers;

use App\Filament\Resources\Mangas\Actions\AsistenciaAction;
use App\Filament\Resources\Mangas\MangaResource;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Services\Scoring;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParticipacionsRelationManager extends RelationManager
{
    protected static string $relationship = 'participacions';

    protected static ?string $title = 'Participaciones y pesajes';

    protected static ?string $modelLabel = 'participación';

    /** Criterio que manda en el formulario: el de la sección de la manga. */
    private function criterio(): string
    {
        return $this->getOwnerRecord()->seccion?->criterio ?? Seccion::CRITERIO_PESO;
    }

    public function form(Schema $schema): Schema
    {
        $criterioFila = fn (Get $get): string => $this->criterio();
        $criterioForm = fn (Get $get): string => $this->criterio();

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
                Toggle::make('plica')
                    ->label('Entregó plica')
                    ->default(true),
                TextInput::make('pieza_mayor_gramos')
                    ->label('Pieza mayor')
                    ->suffix('g')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('El pez más grande del pesaje. Si apuntas pez a pez, se calcula solo.')
                    ->visible(fn (Get $get): bool => $criterioForm($get) !== Seccion::CRITERIO_MEDIDA)
                    ->nullable(),
                Repeater::make('capturas')
                    ->relationship()
                    ->label(fn (Get $get): string => $criterioForm($get) === Seccion::CRITERIO_MEDIDA
                        ? 'Capturas (una por pez)'
                        : 'Capturas')
                    ->schema([
                        TextInput::make('piezas')
                            ->label('Piezas')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->live(onBlur: true)
                            // En medida se cuenta un pez por línea: nada que teclear.
                            ->visible(fn (Get $get): bool => $criterioFila($get) !== Seccion::CRITERIO_MEDIDA)
                            ->required(fn (Get $get): bool => $criterioFila($get) !== Seccion::CRITERIO_MEDIDA),
                        TextInput::make('peso_gramos')
                            ->label('Peso')
                            ->suffix('g')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => $criterioFila($get) !== Seccion::CRITERIO_MEDIDA)
                            ->required(fn (Get $get): bool => $criterioFila($get) === Seccion::CRITERIO_PESO),
                        TextInput::make('medida_mm')
                            ->label('Medida')
                            ->suffix('cm')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.5)
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => $criterioFila($get) === Seccion::CRITERIO_MEDIDA)
                            ->required(fn (Get $get): bool => $criterioFila($get) === Seccion::CRITERIO_MEDIDA)
                            ->formatStateUsing(fn ($state) => filled($state) ? $state / 10 : null)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) round(((float) $state) * 10) : null),
                        TextInput::make('nota')
                            ->label('Nota'),
                    ])
                    ->columns(['default' => 1, 'sm' => 4])
                    ->defaultItems(1)
                    ->addActionLabel(fn (Get $get): string => $criterioForm($get) === Seccion::CRITERIO_MEDIDA
                        ? 'Añadir otro pez'
                        : 'Añadir otra línea')
                    ->columnSpanFull(),
                // El sistema suma solo: el admin no hace cuentas.
                Placeholder::make('total')
                    ->label('Total apuntado')
                    ->content(function (Get $get) use ($criterioForm): string {
                        $filas = collect($get('capturas') ?? []);

                        if ($criterioForm($get) === Seccion::CRITERIO_MEDIDA) {
                            $medidas = $filas->pluck('medida_mm')->filter(fn ($v) => filled($v));
                            $peces = $medidas->count();
                            $cm = (int) round($medidas->sum(fn ($v) => (float) $v) * 10);

                            return ($peces === 1 ? '1 pez' : "{$peces} peces").' · '.Scoring::formatMedida($cm);
                        }

                        $piezas = (int) $filas->sum(fn ($fila) => (int) ($fila['piezas'] ?? 0));
                        $gramos = (int) $filas->sum(fn ($fila) => (int) ($fila['peso_gramos'] ?? 0));

                        return ($piezas === 1 ? '1 pieza' : "{$piezas} piezas").' · '.Scoring::formatPeso($gramos);
                    })
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
            // Lo primero y lo grande: pesar. Añadir una participación a mano queda como
            // opción secundaria (sirve para un pez a pez con notas).
            ->headerActions([
                Action::make('pesaje')
                    ->label('Pesaje rápido')
                    ->icon(Heroicon::OutlinedScale)
                    ->url(fn (): string => MangaResource::getUrl('pesaje', ['record' => $this->getOwnerRecord()])),
                AsistenciaAction::make(fn (): Manga => $this->getOwnerRecord()),
                CreateAction::make()
                    ->label('Añadir a mano')
                    ->color('gray')
                    ->outlined()
                    // Toda participación compite en la sección de su manga.
                    ->mutateDataUsing(function (array $data): array {
                        $data['seccion_id'] = $this->getOwnerRecord()->seccion_id;

                        return $data;
                    }),
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
