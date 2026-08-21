{{-- Filas de una clasificación (un grupo de sección, ya calculado por Scoring).
     Espera: $grupo · $modo ('manga' | 'temporada') · $socioId opcional para resaltar al que mira. --}}
@php $socioId ??= null; @endphp

<div class="plica-lista">
    @foreach ($grupo->filas as $fila)
        <div @class(['plica-fila', 'plica-yo' => $socioId && $fila->socio->id === $socioId])>
            <div @class(['plica-pos', 'plica-pos-podio' => $fila->puesto <= 3])>{{ $fila->puesto }}º</div>
            <div class="plica-quien">
                <div class="plica-nombre">{{ $fila->socio->nombre }}</div>
                <div class="plica-detalle">
                    @if ($modo === 'temporada')
                        {{ $fila->mangas }} {{ $fila->mangas === 1 ? 'manga' : 'mangas' }}
                    @else
                        {{ \App\Services\Scoring::valorSecundario($grupo->criterio, $fila) }}
                    @endif
                </div>
            </div>
            <div class="plica-valor">
                @if ($modo === 'temporada' && $grupo->sistema === \App\Models\Seccion::SISTEMA_PUESTOS)
                    {{ \App\Services\Scoring::formatPuntos($fila->puntos) }} pts
                    <small>{{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}</small>
                @else
                    {{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}
                @endif
            </div>
        </div>
    @endforeach
</div>
