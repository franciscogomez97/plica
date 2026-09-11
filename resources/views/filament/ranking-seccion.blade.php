<x-filament-panels::page>
    @php
        $cuadro = $this->getCuadro();
        $conPuntos = $cuadro && ($cuadro->sistema === \App\Models\Seccion::SISTEMA_PUESTOS || $cuadro->puntosParticipacion > 0 || ($cuadro->puntosNoAsistencia ?? 0) !== 0);
        $unidad = $cuadro ? match ($cuadro->criterio) {
            \App\Models\Seccion::CRITERIO_MEDIDA => 'cm',
            \App\Models\Seccion::CRITERIO_PIEZAS => 'piezas',
            default => 'kg',
        } : '';
        $conPiezas = $cuadro && $cuadro->criterio !== \App\Models\Seccion::CRITERIO_PIEZAS;

        // «4,350 kg» → número y unidad por separado: en móvil solo se enseña el número.
        $partir = fn (string $texto): array => array_pad(explode(' ', $texto, 2), 2, '');
        // «Mario López» → «Mario L.» para la columna fija del móvil.
        $abreviar = function (string $nombre): string {
            $partes = preg_split('/\s+/u', trim($nombre)) ?: [$nombre];

            return $partes[0].(isset($partes[1]) ? ' '.mb_substr($partes[1], 0, 1).'.' : '');
        };
    @endphp

    @include('filament.partials.estilos')


    @if ($cuadro === null)
        <x-filament::section>
            <p style="opacity:.7">No hay temporada activa.</p>
        </x-filament::section>
    @elseif ($cuadro->mangas->isEmpty())
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTableCells" />
                    {{ $cuadro->seccion->nombre }}
                </span>
            </x-slot>
            <x-slot name="description">{{ $cuadro->reglas }}</x-slot>
            <p style="opacity:.7">Aún no hay mangas celebradas de esta sección en la temporada. En cuanto cierres la primera, aquí saldrá el cuadro manga a manga.</p>
        </x-filament::section>
    @else
        {{-- Compartir: enlace público de la sección (cualquiera con el enlace lo ve). --}}
        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.75rem">
            @include('partials.compartir', [
                'titulo' => 'Ranking '.$cuadro->nombre.' · '.auth()->user()->club->nombre,
                'texto' => \App\Services\Compartir::textoRanking(auth()->user()->club, $this->getTemporada(), $cuadro),
                'url' => $cuadro->seccion->urlPublica(),
            ])
            <a href="{{ $cuadro->seccion->urlPublica() }}" target="_blank" rel="noopener" style="font-size:.85rem; opacity:.7; text-decoration:underline">Ver la página pública</a>
        </div>

        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTableCells" />
                    {{ $cuadro->seccion->nombre }} · {{ $cuadro->mangas->count() === 1 ? '1 manga' : $cuadro->mangas->count().' mangas' }}
                </span>
            </x-slot>
            <x-slot name="description">{{ $cuadro->reglas }}</x-slot>

            @include('filament.partials.cuadro', ['urlManga' => fn ($manga) => \App\Filament\Resources\Mangas\MangaResource::getUrl('clasificacion', ['record' => $manga])])
        </x-filament::section>
    @endif
</x-filament-panels::page>
