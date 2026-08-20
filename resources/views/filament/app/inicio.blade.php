<x-filament-panels::page>
    @php
        $socio = $this->getSocio();
        $temporada = $this->getTemporada();
        $proximas = $this->getProximasMangas();
        $rankingGrupos = $this->getRanking();
        $ultimaManga = $this->getUltimaManga();
        $clasifGrupos = $this->getClasificacionUltimaManga();
    @endphp

    @include('filament.partials.estilos')

    <x-filament::section>
        <x-slot name="heading">📅 Próximas mangas</x-slot>
        @if ($proximas->isEmpty())
            <p style="opacity:.7">No hay mangas programadas ahora mismo.</p>
        @else
            <div class="plica-lista">
                @foreach ($proximas as $manga)
                    <div class="plica-fila">
                        <div class="plica-quien">
                            <div class="plica-nombre">{{ $manga->nombre }}</div>
                            <div class="plica-detalle">{{ $manga->lugar ?? 'Lugar por confirmar' }}</div>
                        </div>
                        <div class="plica-valor">
                            {{ $manga->fecha->format('d/m') }}
                            <small>{{ $manga->fecha->format('Y') }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    @foreach ($clasifGrupos as $grupo)
        <x-filament::section>
            <x-slot name="heading">🎣 {{ $ultimaManga->nombre }} — {{ $grupo->nombre }}</x-slot>
            <div class="plica-lista">
                @foreach ($grupo->filas as $fila)
                    <div @class(['plica-fila', 'plica-yo' => $socio && $fila->socio->id === $socio->id])>
                        <div @class(['plica-pos', 'plica-pos-podio' => $fila->puesto <= 3])>{{ $fila->puesto }}º</div>
                        <div class="plica-quien">
                            <div class="plica-nombre">{{ $fila->socio->nombre }}</div>
                            <div class="plica-detalle">{{ \App\Services\Scoring::valorSecundario($grupo->criterio, $fila) }}</div>
                        </div>
                        <div class="plica-valor">{{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endforeach

    @foreach ($rankingGrupos as $grupo)
        <x-filament::section>
            <x-slot name="heading">🏆 Ranking {{ $temporada?->nombre }} — {{ $grupo->nombre }}</x-slot>
            <div class="plica-lista">
                @foreach ($grupo->filas as $fila)
                    <div @class(['plica-fila', 'plica-yo' => $socio && $fila->socio->id === $socio->id])>
                        <div @class(['plica-pos', 'plica-pos-podio' => $fila->puesto <= 3])>{{ $fila->puesto }}º</div>
                        <div class="plica-quien">
                            <div class="plica-nombre">{{ $fila->socio->nombre }}</div>
                            <div class="plica-detalle">{{ $fila->mangas }} {{ $fila->mangas === 1 ? 'manga' : 'mangas' }}</div>
                        </div>
                        <div class="plica-valor">
                            @if ($grupo->sistema === \App\Models\Seccion::SISTEMA_PUESTOS)
                                {{ \App\Services\Scoring::formatPuntos($fila->puntos) }} pts
                                <small>{{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}</small>
                            @else
                                {{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endforeach

    @if ($rankingGrupos->isEmpty())
        <x-filament::section>
            <x-slot name="heading">🏆 Ranking</x-slot>
            <p style="opacity:.7">Aún no hay mangas celebradas esta temporada.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
