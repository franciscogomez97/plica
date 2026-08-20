<x-filament-panels::page>
    @php
        $socio = $this->getSocio();
        $temporada = $this->getTemporada();
        $proximas = $this->getProximasMangas();
        $rankingGrupos = $this->getRanking();
        $ultimaManga = $this->getUltimaManga();
        $clasifGrupos = $this->getClasificacionUltimaManga();
    @endphp

    <style>
        .plica-tabla { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .plica-tabla th { text-align: left; padding: .55rem .9rem; opacity: .6; font-weight: 600; }
        .plica-tabla td { padding: .55rem .9rem; border-top: 1px solid rgba(128, 128, 128, .18); }
        .plica-tabla .num { text-align: right; font-variant-numeric: tabular-nums; }
        .plica-podio { font-weight: 700; }
        .plica-yo { background: rgba(16, 185, 129, .1); }
    </style>

    <x-filament::section>
        <x-slot name="heading">Próximas mangas</x-slot>
        @if ($proximas->isEmpty())
            <p style="opacity:.7">No hay mangas programadas ahora mismo.</p>
        @else
            <div style="overflow-x:auto">
                <table class="plica-tabla">
                    <thead>
                        <tr><th>Manga</th><th>Fecha</th><th>Lugar</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($proximas as $manga)
                            <tr>
                                <td>{{ $manga->nombre }}</td>
                                <td>{{ $manga->fecha->format('d/m/Y') }}</td>
                                <td>{{ $manga->lugar ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    @foreach ($clasifGrupos as $grupo)
        <x-filament::section>
            <x-slot name="heading">Última manga · {{ $ultimaManga->nombre }} — {{ $grupo->nombre }}</x-slot>
            <div style="overflow-x:auto">
                <table class="plica-tabla">
                    <thead>
                        <tr><th>Puesto</th><th>Socio</th><th class="num">Piezas</th><th class="num">Peso</th><th class="num">Medida</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($grupo->filas as $fila)
                            <tr @class(['plica-podio' => $fila->puesto <= 3, 'plica-yo' => $socio && $fila->socio->id === $socio->id])>
                                <td>{{ $fila->puesto }}º</td>
                                <td>{{ $fila->socio->nombre }}</td>
                                <td class="num">{{ $fila->piezas }}</td>
                                <td class="num">{{ $fila->peso > 0 ? \App\Services\Scoring::formatPeso($fila->peso) : '—' }}</td>
                                <td class="num">{{ $fila->medida > 0 ? \App\Services\Scoring::formatMedida($fila->medida) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endforeach

    @foreach ($rankingGrupos as $grupo)
        <x-filament::section>
            <x-slot name="heading">Ranking {{ $temporada?->nombre }} — {{ $grupo->nombre }} ({{ \App\Models\Seccion::CRITERIOS[$grupo->criterio] ?? $grupo->criterio }})</x-slot>
            <div style="overflow-x:auto">
                <table class="plica-tabla">
                    <thead>
                        <tr><th>Puesto</th><th>Socio</th><th class="num">Mangas</th><th class="num">Piezas</th><th class="num">Peso</th><th class="num">Medida</th><th class="num">Puntos</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($grupo->filas as $fila)
                            <tr @class(['plica-podio' => $fila->puesto <= 3, 'plica-yo' => $socio && $fila->socio->id === $socio->id])>
                                <td>{{ $fila->puesto }}º</td>
                                <td>{{ $fila->socio->nombre }}</td>
                                <td class="num">{{ $fila->mangas }}</td>
                                <td class="num">{{ $fila->piezas }}</td>
                                <td class="num">{{ $fila->peso > 0 ? \App\Services\Scoring::formatPeso($fila->peso) : '—' }}</td>
                                <td class="num">{{ $fila->medida > 0 ? \App\Services\Scoring::formatMedida($fila->medida) : '—' }}</td>
                                <td class="num plica-podio">{{ \App\Services\Scoring::formatPuntos($fila->puntos) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endforeach

    @if ($rankingGrupos->isEmpty())
        <x-filament::section>
            <x-slot name="heading">Ranking</x-slot>
            <p style="opacity:.7">Aún no hay mangas celebradas esta temporada.</p>
        </x-filament::section>
    @else
        <p style="opacity:.5; font-size:.75rem">Puntuación provisional del piloto: acumulado por sección en mangas celebradas, según el criterio de cada sección.</p>
    @endif
</x-filament-panels::page>
