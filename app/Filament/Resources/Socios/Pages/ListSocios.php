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
                ->modalDescription('Pega la lista tal cual la tengas: del WhatsApp, del Excel o escrita a mano. Un socio por línea. Teléfono y email, opcionales, detrás del nombre: con el teléfono, «Dar acceso» le abre su WhatsApp directamente.')
                ->modalSubmitActionLabel('Añadir')
                ->schema([
                    Textarea::make('lista')
                        ->label('Un socio por línea')
                        ->placeholder("Mario López 600 11 22 33\nPaco Jiménez, 611 22 33 44, paco@gmail.com\nAndrés Molina")
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
            // Dar acceso a todo el club en una tarde: la lista de socios, cada uno con su
            // enlace personal y su botón de WhatsApp. Se mandan uno a uno (son personales:
            // con el enlace de otro entrarías como él), por eso no hay «copiar todos».
            Action::make('enlaces')
                ->label('Dar acceso')
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->modalHeading('Dar acceso a los socios')
                ->modalDescription('Cada socio tiene su propio enlace, de un solo uso. Mándaselo por WhatsApp a cada uno: al abrirlo crea su cuenta con su email y su contraseña. Si ya tiene cuenta, el enlace le sirve para poner una contraseña nueva. Mientras no lo use, puedes volver a mandárselo.')
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
