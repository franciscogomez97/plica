<?php

namespace App\Filament\Resources\Socios\Pages;

use App\Filament\Concerns\PestanasDeSeccion;
use App\Filament\Resources\Socios\SocioResource;
use App\Models\Seccion;
use App\Models\Socio;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListSocios extends ListRecords
{
    use PestanasDeSeccion;

    protected static string $resource = SocioResource::class;

    /** @var Collection<int, Seccion>|null */
    protected ?Collection $seccionesDelClub = null;

    protected function getHeaderActions(): array
    {
        return [
            // Los clubes no tienen CSV: tienen la lista en el WhatsApp o en un
            // papel. Se pega tal cual y ya está.
            Action::make('varios')
                ->label('Añadir varios')
                ->icon(Heroicon::OutlinedUserGroup)
                ->modalHeading('Añadir varios socios de golpe')
                ->modalDescription(fn (): string => 'Pega la lista tal cual la tengas: del WhatsApp, del Excel o escrita a mano. Un socio por línea. Teléfono y email, opcionales, detrás del nombre: con el teléfono, «Dar acceso» le abre su WhatsApp directamente.'
                    .(($seccion = $this->seccionPorDefecto()) ? " Se apuntan a {$seccion->nombre}." : ''))
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
                    // Desde la pestaña de una sección, los nuevos ya son de esa sección.
                    $resultado = auth()->user()->club->altaDeSocios($data['lista'], $this->seccionPorDefecto());
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
            CreateAction::make()
                ->url(fn (): string => SocioResource::getUrl('create', array_filter(['seccion' => $this->seccionPorDefecto()?->id]))),
            // Dar acceso a todo el club en una tarde: la lista de socios, cada uno con su
            // enlace personal y su botón de WhatsApp. Se mandan uno a uno (son personales:
            // con el enlace de otro entrarías como él), por eso no hay «copiar todos».
            Action::make('enlaces')
                ->label('Dar acceso')
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->modalHeading(fn (): string => ($seccion = $this->seccionActiva()) ? "Dar acceso a los socios de {$seccion->nombre}" : 'Dar acceso a los socios')
                ->modalDescription('Cada socio tiene su propio enlace, de un solo uso. Mándaselo por WhatsApp a cada uno: al abrirlo crea su cuenta con su email y su contraseña. Si ya tiene cuenta, el enlace le sirve para poner una contraseña nueva. Mientras no lo use, puedes volver a mandárselo.')
                ->modalContent(fn (): View => view('filament.socios.enlaces', [
                    'club' => auth()->user()->club,
                    'socios' => Socio::query()
                        ->where('club_id', auth()->user()->club_id)
                        ->where('activo', true)
                        ->when($this->seccionActiva(), fn (Builder $query, Seccion $seccion) => $query->whereHas('seccions', fn (Builder $q) => $q->whereKey($seccion->id)))
                        ->orderBy('nombre')
                        ->get(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
        ];
    }

    protected function consultaDeSeccion(Builder $query, Seccion $seccion): Builder
    {
        return $query->whereHas('seccions', fn (Builder $q) => $q->whereKey($seccion->id));
    }

    protected function etiquetaDeTodas(): string
    {
        return 'Todos';
    }
}
