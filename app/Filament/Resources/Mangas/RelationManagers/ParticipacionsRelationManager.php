<?php

namespace App\Filament\Resources\Mangas\RelationManagers;

use App\Models\Seccion;
use App\Models\Socio;
use App\Services\Scoring;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
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
                    ->columns(4)
                    ->defaultItems(1)
                    ->addActionLabel('Añadir línea de captura')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with(['socio', 'seccion', 'capturas']))
            ->columns([
                TextColumn::make('socio.nombre')
                    ->label('Socio')
                    ->searchable(),
                TextColumn::make('seccion.nombre')
                    ->label('Sección')
                    ->placeholder('—'),
                IconColumn::make('plica')
                    ->label('Plica')
                    ->boolean(),
                TextColumn::make('piezas')
                    ->label('Piezas')
                    ->state(fn ($record): int => $record->piezasTotal()),
                TextColumn::make('peso')
                    ->label('Peso')
                    ->state(fn ($record): string => Scoring::formatPeso($record->pesoTotal())),
                TextColumn::make('medida')
                    ->label('Medida')
                    ->state(fn ($record): string => $record->medidaTotal() > 0 ? Scoring::formatMedida($record->medidaTotal()) : '—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir participación'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
