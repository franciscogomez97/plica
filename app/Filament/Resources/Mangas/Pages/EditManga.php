<?php

namespace App\Filament\Resources\Mangas\Pages;

use App\Filament\Resources\Mangas\Actions\ConvocarAction;
use App\Filament\Resources\Mangas\MangaResource;
use App\Models\Manga;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditManga extends EditRecord
{
    protected static string $resource = MangaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Con el lugar y la ubicación ya guardados, la convocatoria sale completa.
            ConvocarAction::make(fn (): Manga => $this->getRecord()),
            Action::make('pesaje')
                ->label('Pesaje rápido')
                ->icon('heroicon-o-scale')
                ->url(fn (): string => MangaResource::getUrl('pesaje', ['record' => $this->getRecord()])),
            Action::make('clasificacion')
                ->label('Ver clasificación')
                ->icon('heroicon-o-trophy')
                ->color('success')
                ->url(fn (): string => MangaResource::getUrl('clasificacion', ['record' => $this->getRecord()])),
            DeleteAction::make()
                // Con pesajes dentro no se borra: primero habría que vaciarla.
                ->visible(fn (): bool => $this->getRecord()->participacions()->doesntExist()),
        ];
    }
}
