<?php

namespace App\Filament\Resources\Socios\Tables;

use App\Models\Socio;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SociosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('— sin email —')
                    ->searchable(),
                IconColumn::make('user_id')
                    ->label('Cuenta')
                    ->boolean()
                    ->tooltip(fn (Socio $record): string => $record->user_id
                        ? 'Tiene cuenta y puede ver los rankings'
                        : 'Sin cuenta (el admin gestiona sus datos)'),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('nombre')
            ->recordActions([
                Action::make('invitar')
                    ->label('Invitar')
                    ->icon('heroicon-o-link')
                    ->visible(fn (Socio $record): bool => $record->user_id === null)
                    ->modalHeading('Link de invitación')
                    ->modalDescription('Envíaselo por WhatsApp: al abrirlo podrá crear su cuenta y ver los rankings.')
                    ->modalContent(fn (Socio $record) => view('filament.invite-link', ['url' => $record->inviteUrl()]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
