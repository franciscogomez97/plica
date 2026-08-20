<?php

namespace App\Filament\Resources\Temporadas;

use App\Filament\Resources\Temporadas\Pages\CreateTemporada;
use App\Filament\Resources\Temporadas\Pages\EditTemporada;
use App\Filament\Resources\Temporadas\Pages\ListTemporadas;
use App\Filament\Resources\Temporadas\Schemas\TemporadaForm;
use App\Filament\Resources\Temporadas\Tables\TemporadasTable;
use App\Models\Temporada;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TemporadaResource extends Resource
{
    protected static ?string $model = Temporada::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $modelLabel = 'temporada';

    protected static ?string $pluralModelLabel = 'temporadas';

    protected static ?int $navigationSort = 30;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('club_id', auth()->user()->club_id);
    }

    public static function form(Schema $schema): Schema
    {
        return TemporadaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TemporadasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemporadas::route('/'),
            'create' => CreateTemporada::route('/create'),
            'edit' => EditTemporada::route('/{record}/edit'),
        ];
    }
}
