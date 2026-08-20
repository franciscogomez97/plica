<x-filament-panels::page>
    @php
        $manga = $this->getRecord();
        $grupos = $this->getClasificacion();
    @endphp

    @include('filament.partials.estilos-tabla')

    @if ($grupos->isEmpty())
        <x-filament::section>
            <p style="opacity:.7">Aún no hay participaciones en esta manga. Añádelas en la pestaña «Participaciones y pesajes».</p>
        </x-filament::section>
    @endif

    @foreach ($grupos as $grupo)
        <x-filament::section>
            <x-slot name="heading">
                {{ $grupo->nombre }} · clasificación por {{ \App\Models\Seccion::CRITERIOS[$grupo->criterio] ?? $grupo->criterio }}
                — {{ $manga->fecha->format('d/m/Y') }}{{ $manga->lugar ? ' · '.$manga->lugar : '' }}
            </x-slot>
            <div style="overflow-x:auto">
                <table class="plica-tabla">
                    <thead>
                        <tr>
                            <th>Puesto</th>
                            <th>Socio</th>
                            <th class="num">Piezas</th>
                            <th class="num">Peso</th>
                            <th class="num">Medida</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grupo->filas as $fila)
                            <tr @class(['plica-podio' => $fila->puesto <= 3])>
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

    @if ($manga->estado === \App\Models\Manga::ESTADO_PROGRAMADA)
        <p style="opacity:.6; font-size:.8rem">Clasificación provisional: la manga aún no está marcada como celebrada, así que no puntúa para el ranking de temporada.</p>
    @endif
</x-filament-panels::page>
