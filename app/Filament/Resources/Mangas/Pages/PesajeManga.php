<?php

namespace App\Filament\Resources\Mangas\Pages;

use App\Filament\Resources\Mangas\Actions\AsistenciaAction;
use App\Filament\Resources\Mangas\Actions\ConvocarAction;
use App\Filament\Resources\Mangas\MangaResource;
use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Socio;
use App\Services\PesajeRapido;
use App\Services\Scoring;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Pesaje rápido: todos los asistentes de la manga en una lista, una casilla
 * por dato, y cada casilla se guarda sola al salir de ella. Enter salta a la
 * siguiente. Pensado para teclear 40 plicas del tirón desde el móvil sin
 * abrir ni un formulario. El detalle pez a pez sigue en «Datos de la manga».
 */
class PesajeManga extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MangaResource::class;

    protected string $view = 'filament.mangas.pesaje';

    /** @var array<int, array{piezas: string, peso: string, medidas: string}> */
    public array $filas = [];

    /** @var array<int, array{tipo: string, texto: string}> */
    public array $estados = [];

    public ?string $nuevoSocioId = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->cargarFilas();
    }

    public function getTitle(): string
    {
        return 'Pesaje — '.$this->getRecord()->nombre;
    }

    public function getSubheading(): ?string
    {
        $manga = $this->getRecord();
        $n = count($this->filas);
        $confirmados = $manga->estado === Manga::ESTADO_PROGRAMADA ? $manga->confirmacions()->count() : 0;

        return implode(' · ', array_filter([
            $manga->fecha->format('d/m/Y'),
            $manga->lugar,
            $n === 1 ? '1 participante' : "{$n} participantes",
            $confirmados > 0 ? ($confirmados === 1 ? '1 confirmó que vendría' : "{$confirmados} confirmaron que vendrían") : null,
        ]));
    }

    /**
     * Nombres de los socios que marcaron «asistiré» (intención, no asistencia).
     *
     * @return Collection<int, string>
     */
    public function getConfirmados(): Collection
    {
        return $this->getRecord()
            ->confirmacions()
            ->with('socio')
            ->get()
            ->map(fn ($c) => $c->socio->nombre)
            ->sort()
            ->values();
    }

    /**
     * Grupos por sección con sus participaciones, ordenadas por nombre de socio
     * (el admin tiene las plicas en la mano y busca por nombre).
     *
     * @return Collection<int, object{nombre: string, criterio: string, participaciones: Collection<int, Participacion>}>
     */
    public function getGrupos(): Collection
    {
        $participaciones = $this->getRecord()
            ->participacions()
            ->with(['socio', 'seccion', 'capturas'])
            ->get()
            ->sortBy(fn (Participacion $p) => Str::lower(Str::ascii($p->socio->nombre)))
            ->values();

        return Scoring::agruparPorSeccion($participaciones);
    }

    /**
     * Socios activos del club que aún no están en la manga, en dos grupos: los de
     * la sección de la manga primero y, aparte, los demás (por si viene un invitado).
     *
     * @return array<string, array<int, string>> grupo => [id => nombre]
     */
    public function getSociosDisponibles(): array
    {
        $manga = $this->getRecord();
        $deLaSeccion = $manga->seccion->socios()->pluck('socios.id');

        $todos = Socio::query()
            ->where('club_id', auth()->user()->club_id)
            ->where('activo', true)
            ->whereNotIn('id', $manga->participacions()->select('socio_id'))
            ->orderBy('nombre')
            ->get();

        return array_filter([
            "Socios de {$manga->seccion->nombre}" => $todos->whereIn('id', $deLaSeccion)->pluck('nombre', 'id')->all(),
            'Otros socios del club' => $todos->whereNotIn('id', $deLaSeccion)->pluck('nombre', 'id')->all(),
        ]);
    }

    /** Cualquier casilla que cambia guarda su fila: no hay botón de guardar. */
    public function updated(string $name, mixed $value): void
    {
        if (preg_match('/^filas\.(\d+)\./', $name, $m)) {
            $this->guardar((int) $m[1]);
        }
    }

    /** Alguien que ha venido sin estar en la lista: se apunta y se le enfoca la casilla. */
    public function updatedNuevoSocioId(?string $value): void
    {
        $this->nuevoSocioId = null;

        if (! filled($value)) {
            return;
        }

        $manga = $this->getRecord();
        $socio = Socio::query()
            ->where('club_id', auth()->user()->club_id)
            ->where('activo', true)
            ->find((int) $value);

        if ($socio === null || $manga->participacions()->where('socio_id', $socio->id)->exists()) {
            return;
        }

        $participacion = $manga->participacions()->create([
            'socio_id' => $socio->id,
            'seccion_id' => $manga->seccion_id,
        ]);
        $participacion->load(['seccion', 'capturas']);

        $this->filas[$participacion->id] = PesajeRapido::fila($participacion);
        $this->estados[$participacion->id] = ['tipo' => 'vacio', 'texto' => 'Sin capturas'];

        $this->dispatch('pesaje-enfocar', id: $participacion->id);
    }

    /** Quitar a alguien apuntado por error. Solo sin capturas: con datos, no se pierde nada. */
    public function quitar(int $participacionId): void
    {
        $participacion = $this->getRecord()->participacions()->with('capturas')->find($participacionId);

        if ($participacion === null || $participacion->capturas->isNotEmpty()) {
            return;
        }

        $participacion->delete();
        unset($this->filas[$participacionId], $this->estados[$participacionId]);
    }

    protected function guardar(int $participacionId): void
    {
        $participacion = $this->getRecord()
            ->participacions()
            ->with(['seccion', 'capturas'])
            ->find($participacionId);

        if ($participacion === null) {
            return;
        }

        $criterio = $participacion->seccion?->criterio ?? Seccion::CRITERIO_PESO;

        try {
            PesajeRapido::aplicar($participacion, $criterio, $this->filas[$participacionId] ?? []);
        } catch (InvalidArgumentException $e) {
            $this->estados[$participacionId] = ['tipo' => 'error', 'texto' => $e->getMessage()];

            return;
        }

        $this->filas[$participacionId] = PesajeRapido::fila($participacion);
        $this->estados[$participacionId] = [
            'tipo' => $participacion->capturas->isEmpty() ? 'vacio' : 'guardado',
            'texto' => PesajeRapido::total($participacion, $criterio),
        ];
    }

    private function cargarFilas(): void
    {
        $this->filas = [];
        $this->estados = [];

        $participaciones = $this->getRecord()->participacions()->with(['seccion', 'capturas'])->get();

        foreach ($participaciones as $participacion) {
            $criterio = $participacion->seccion?->criterio ?? Seccion::CRITERIO_PESO;
            $this->filas[$participacion->id] = PesajeRapido::fila($participacion);
            $this->estados[$participacion->id] = [
                'tipo' => $participacion->capturas->isEmpty() ? 'vacio' : 'ok',
                'texto' => PesajeRapido::total($participacion, $criterio),
            ];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ConvocarAction::make(fn (): Manga => $this->getRecord()),
            AsistenciaAction::make(fn (): Manga => $this->getRecord())
                ->after(fn () => $this->cargarFilas()),
            Action::make('clasificacion')
                ->label('Clasificación')
                ->icon(Heroicon::OutlinedTrophy)
                ->color('success')
                ->url(fn (): string => MangaResource::getUrl('clasificacion', ['record' => $this->getRecord()])),
            Action::make('editar')
                ->label('Datos de la manga')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->url(fn (): string => MangaResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    /** El cierre natural del pesaje: la manga pasa a celebrada y se enseña la clasificación. */
    public function celebrarAction(): Action
    {
        return Action::make('celebrar')
            ->label('Cerrar la manga y ver la clasificación')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->size(Size::Large)
            ->visible(fn (): bool => $this->getRecord()->estado === Manga::ESTADO_PROGRAMADA)
            ->requiresConfirmation()
            ->modalHeading('¿Marcar la manga como celebrada?')
            ->modalDescription('Pasa a contar para el ranking de temporada. Los pesajes se pueden corregir después.')
            ->modalSubmitActionLabel('Sí, cerrar la manga')
            ->action(function (): void {
                $this->getRecord()->update(['estado' => Manga::ESTADO_CELEBRADA]);
                $this->redirect(MangaResource::getUrl('clasificacion', ['record' => $this->getRecord()]), navigate: true);
            });
    }
}
