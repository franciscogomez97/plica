<?php

namespace App\Filament\Resources\Mangas\Pages;

use App\Filament\Resources\Mangas\MangaResource;
use App\Models\Manga;
use App\Services\Scoring;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ClasificacionManga extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MangaResource::class;

    protected string $view = 'filament.mangas.clasificacion';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Clasificación — '.$this->getRecord()->nombre;
    }

    public function getClasificacion(): Collection
    {
        return Scoring::clasificacionManga($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pesaje')
                ->label('Corregir pesajes')
                ->icon('heroicon-o-scale')
                ->color('gray')
                ->url(fn (): string => MangaResource::getUrl('pesaje', ['record' => $this->getRecord()])),
            Action::make('marcarCelebrada')
                ->label('Marcar como celebrada')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->estado === Manga::ESTADO_PROGRAMADA)
                ->requiresConfirmation()
                ->modalDescription('La manga contará para el ranking de temporada.')
                ->action(function (): void {
                    $this->getRecord()->update(['estado' => Manga::ESTADO_CELEBRADA]);
                }),
            Action::make('reabrir')
                ->label('Volver a programada')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->estado === Manga::ESTADO_CELEBRADA)
                ->action(function (): void {
                    $this->getRecord()->update(['estado' => Manga::ESTADO_PROGRAMADA]);
                }),
        ];
    }
}
