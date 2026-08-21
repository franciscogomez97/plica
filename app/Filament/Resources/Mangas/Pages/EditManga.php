<?php

namespace App\Filament\Resources\Mangas\Pages;

use App\Filament\Resources\Mangas\MangaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditManga extends EditRecord
{
    protected static string $resource = MangaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clasificacion')
                ->label('Ver clasificación')
                ->icon('heroicon-o-trophy')
                ->color('success')
                ->url(fn (): string => MangaResource::getUrl('clasificacion', ['record' => $this->getRecord()])),
            DeleteAction::make(),
        ];
    }
}
