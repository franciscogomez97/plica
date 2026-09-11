<?php

namespace App\Filament\Resources\Socios\Pages;

use App\Filament\Resources\Socios\SocioResource;
use App\Models\Socio;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class ListSocios extends ListRecords
{
    protected static string $resource = SocioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Los clubes no tienen CSV: tienen la lista en el WhatsApp o en un
            // papel. Se pega tal cual y ya está.
            Action::make('varios')
                ->label('Añadir varios')
                ->icon(Heroicon::OutlinedUserGroup)
                ->modalHeading('Añadir varios socios de golpe')
                ->modalDescription('Pega la lista tal cual la tengas: del WhatsApp, del Excel o escrita a mano. Un socio por línea. El email es opcional, detrás del nombre.')
                ->modalSubmitActionLabel('Añadir')
                ->schema([
                    Textarea::make('lista')
                        ->label('Un socio por línea')
                        ->placeholder("Mario López\nPaco Jiménez, paco@gmail.com\nAndrés Molina")
                        ->rows(10)
                        ->autofocus()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $resultado = auth()->user()->club->altaDeSocios($data['lista']);
                    $creados = count($resultado['creados']);

                    $notificacion = Notification::make()
                        ->title(match ($creados) {
                            0 => 'Ningún socio nuevo',
                            1 => '1 socio añadido',
                            default => "{$creados} socios añadidos",
                        })
                        ->success();

                    if ($resultado['repetidos'] !== []) {
                        $notificacion
                            ->warning()
                            ->body('Ya estaban: '.implode(', ', $resultado['repetidos']));
                    }

                    $notificacion->send();
                }),
            CreateAction::make(),
            // Los enlaces de acceso de todos a la vez: el alta de un club en cinco minutos.
            Action::make('enlaces')
                ->label('Enlaces de acceso')
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->modalHeading('Enlaces de acceso para todos')
                ->modalDescription('Un mensaje por socio, listo para pegar en WhatsApp. Cada enlace es de un solo uso: crea la cuenta o, si ya la tiene, le deja poner una contraseña nueva.')
                ->modalContent(fn (): View => view('filament.socios.enlaces', [
                    'club' => auth()->user()->club,
                    'socios' => Socio::query()
                        ->where('club_id', auth()->user()->club_id)
                        ->where('activo', true)
                        ->orderBy('nombre')
                        ->get(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
        ];
    }
}
