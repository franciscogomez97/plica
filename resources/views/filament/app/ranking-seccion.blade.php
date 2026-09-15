<x-filament-panels::page>
    @php
        $club = $this->getClub();
        $socio = $this->getSocio();
        $temporada = $this->getTemporada();
        $cuadro = $this->getCuadro();
        $grupo = $this->getGrupo();
        $ultima = $this->getUltima();

        $conPuntos = $cuadro && ($cuadro->sistema === \App\Models\Seccion::SISTEMA_PUESTOS || $cuadro->puntosParticipacion > 0 || ($cuadro->puntosNoAsistencia ?? 0) !== 0);
        $unidad = $cuadro ? match ($cuadro->criterio) {
            \App\Models\Seccion::CRITERIO_MEDIDA => 'cm',
            \App\Models\Seccion::CRITERIO_PIEZAS => 'piezas',
            default => 'kg',
        } : '';
        $conPiezas = $cuadro && $cuadro->criterio !== \App\Models\Seccion::CRITERIO_PIEZAS;
        $partir = fn (string $texto): array => array_pad(explode(' ', $texto, 2), 2, '');
        $abreviar = function (string $nombre): string {
            $partes = preg_split('/\s+/u', trim($nombre)) ?: [$nombre];

            return $partes[0].(isset($partes[1]) ? ' '.mb_substr($partes[1], 0, 1).'.' : '');
        };
    @endphp

    @include('filament.partials.estilos')

    @if ($grupo === null || $cuadro === null)
        <x-filament::section>
            <p style="opacity:.7">Aún no hay mangas celebradas de esta sección. En cuanto se cierre la primera, aquí saldrá el ranking.</p>
        </x-filament::section>
    @else
        {{-- Solo el cuadro manga a manga: ya lleva el orden general y los totales, una lista aparte sería repetirlo. --}}
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy" />
                    Clasificación general
                    <span class="plica-etiqueta">por {{ mb_strtolower(\App\Models\Seccion::CRITERIOS[$grupo->criterio] ?? $grupo->criterio) }}</span>
                </span>
            </x-slot>
            <x-slot name="description">{{ $grupo->reglas }} {{ $cuadro->mangas->count() === 1 ? '1 manga celebrada.' : $cuadro->mangas->count().' mangas celebradas.' }}</x-slot>
            @include('filament.partials.cuadro', ['urlManga' => fn ($manga) => \App\Filament\App\Pages\ClasificacionManga::getUrl(['manga' => $manga->id])])
            @if ($club && $temporada)
                <div class="plica-mas">
                    @include('partials.compartir', [
                        'titulo' => 'Ranking '.$grupo->nombre.' · '.$club->nombre,
                        'texto' => \App\Services\Compartir::textoRanking($club, $temporada, $grupo),
                        'url' => $cuadro->seccion->urlPublica(),
                    ])
                    @include('partials.compartir-imagen', ['titulo' => 'Ranking '.$grupo->nombre.' · '.$club->nombre, 'url' => \App\Services\Podio::urlRanking($temporada, $cuadro->seccion, $grupo), 'compacto' => true, 'pie' => \App\Services\Compartir::textoRanking($club, $temporada, $grupo)."\n".$cuadro->seccion->urlPublica()])
                </div>
            @endif
        </x-filament::section>

        @if ($ultima)
            <x-filament::section>
                <x-slot name="heading">
                    <span class="plica-h">
                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedScale" />
                        Última manga · {{ $ultima->manga->nombre }}
                    </span>
                </x-slot>
                <x-slot name="description">{{ $ultima->manga->fecha->format('d/m/Y') }}{{ $ultima->manga->lugar ? ' · '.$ultima->manga->lugar : '' }}</x-slot>
                @include('filament.partials.lista-clasificacion', ['grupo' => $ultima->grupo, 'modo' => 'manga', 'socioId' => $socio?->id])
                <div class="plica-mas">
                    <x-filament::button tag="a" size="sm" color="gray" href="{{ \App\Filament\App\Pages\ClasificacionManga::getUrl(['manga' => $ultima->manga->id]) }}" wire:navigate>
                        Ver la manga completa
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
