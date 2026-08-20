<?php

namespace App\Filament\Resources\Mangas;

use App\Filament\Resources\Mangas\Pages\ClasificacionManga;
use App\Filament\Resources\Mangas\Pages\CreateManga;
use App\Filament\Resources\Mangas\Pages\EditManga;
use App\Filament\Resources\Mangas\Pages\ListMangas;
use App\Filament\Resources\Mangas\RelationManagers\ParticipacionsRelationManager;
use App\Filament\Resources\Mangas\Schemas\MangaForm;
use App\Filament\Resources\Mangas\Tables\MangasTable;
use App\Models\Manga;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MangaResource extends Resource
{
    protected static ?string $model = Manga::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $modelLabel = 'manga';

    protected static ?string $pluralModelLabel = 'mangas';

    protected static ?int $navigationSort = 20;

    /** Solo mangas de temporadas del club del admin. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('temporada', fn (Builder $q) => $q->where('club_id', auth()->user()->club_id));
    }

    public static function form(Schema $schema): Schema
    {
        return MangaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MangasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ParticipacionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMangas::route('/'),
            'create' => CreateManga::route('/create'),
            'edit' => EditManga::route('/{record}/edit'),
            'clasificacion' => ClasificacionManga::route('/{record}/clasificacion'),
        ];
    }
}
