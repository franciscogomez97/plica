<?php

namespace App\Filament\Resources\Solicituds;

use App\Filament\Resources\Solicituds\Pages\ListSolicituds;
use App\Filament\Resources\Solicituds\Tables\SolicitudsTable;
use App\Models\Solicitud;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SolicitudResource extends Resource
{
    protected static ?string $model = Solicitud::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $modelLabel = 'solicitud';

    protected static ?string $pluralModelLabel = 'solicitudes de acceso';

    protected static ?int $navigationSort = 90;

    /** Solo el dueño de la plataforma: los admins de club no gestionan esto. */
    public static function canViewAny(): bool
    {
        return auth()->user()?->email === config('plica.superadmin_email');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return SolicitudsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSolicituds::route('/'),
        ];
    }
}
