<?php

namespace App\Filament\Resources\Equipos;

use App\Filament\Resources\Equipos\Pages\CreateEquipo;
use App\Filament\Resources\Equipos\Pages\EditEquipo;
use App\Filament\Resources\Equipos\Pages\ListEquipos;
use App\Filament\Resources\Equipos\Schemas\EquipoForm;
use App\Filament\Resources\Equipos\Tables\EquiposTable;
use App\Models\Equipo;
use App\Models\Seccion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Los equipos de las secciones por equipos (barcos, parejas) de la temporada
 * activa. Solo aparece en el menú si el club tiene alguna sección por equipos:
 * un club de orilla no lo ve.
 */
class EquipoResource extends Resource
{
    protected static ?string $model = Equipo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'equipo';

    protected static ?string $pluralModelLabel = 'equipos';

    protected static ?int $navigationSort = 15;

    public static function shouldRegisterNavigation(): bool
    {
        return static::seccionesPorEquipos()->isNotEmpty();
    }

    /** Las secciones por equipos del club, por nombre. */
    public static function seccionesPorEquipos()
    {
        return Seccion::query()
            ->where('club_id', auth()->user()->club_id)
            ->where('modalidad', Seccion::MODALIDAD_EQUIPOS)
            ->orderBy('nombre')
            ->get();
    }

    /** Solo los equipos del club y de su temporada activa. */
    public static function getEloquentQuery(): Builder
    {
        $temporada = auth()->user()->club?->temporadaActiva();

        return parent::getEloquentQuery()
            ->whereHas('seccion', fn (Builder $q) => $q->where('club_id', auth()->user()->club_id))
            ->where('temporada_id', $temporada?->id ?? 0)
            ->with(['socios', 'seccion']);
    }

    public static function form(Schema $schema): Schema
    {
        return EquipoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquiposTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipos::route('/'),
            'create' => CreateEquipo::route('/create'),
            'edit' => EditEquipo::route('/{record}/edit'),
        ];
    }
}
