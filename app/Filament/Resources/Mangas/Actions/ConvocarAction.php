<?php

namespace App\Filament\Resources\Mangas\Actions;

use App\Models\Manga;
use App\Services\Compartir;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

/**
 * «Convocar por WhatsApp»: modal con el texto de la convocatoria, editable, y
 * el botón de compartir. Lo usan la ficha de la manga y su pesaje.
 */
class ConvocarAction
{
    /** @param  Closure(): Manga  $manga */
    public static function make(Closure $manga, string $name = 'convocar'): Action
    {
        return Action::make($name)
            ->label('Convocar por WhatsApp')
            ->icon(Heroicon::OutlinedMegaphone)
            ->color('success')
            ->visible(fn (): bool => $manga()->estado === Manga::ESTADO_PROGRAMADA)
            ->modalHeading(fn (): string => 'Convocatoria: '.$manga()->nombre)
            ->modalContent(fn (): View => view('filament.mangas.convocatoria', [
                'manga' => $manga(),
                'texto' => Compartir::textoConvocatoria($manga()),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }
}
