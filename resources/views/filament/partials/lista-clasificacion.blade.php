{{-- Filas de una clasificación (un grupo de sección, ya calculado por Scoring).
     Espera: $grupo · $modo ('manga' | 'temporada') · $socioId opcional para resaltar
     al que mira · $limite opcional: solo las N primeras (y la del socio, si queda fuera). --}}
@php
    $socioId ??= null;
    $limite ??= null;
    $criterio = $grupo->criterio;
    $valorDe = fn (object $f): int => $modo === 'temporada'
        ? (int) $f->puntos
        : (int) match ($criterio) {
            \App\Models\Seccion::CRITERIO_MEDIDA => $f->medida,
            \App\Models\Seccion::CRITERIO_PIEZAS => $f->piezas,
            default => $f->peso,
        };
    $filas = $grupo->filas->values();
    $max = max(1, (int) $filas->max($valorDe));
    $visibles = $limite ? $filas->take($limite) : $filas;
    $miFila = $socioId ? $filas->first(fn ($f) => $f->socio->id === $socioId) : null;
    $miFilaOculta = $miFila !== null && $limite && ! $visibles->contains(fn ($f) => $f->socio->id === $socioId);
    $ocultas = $filas->count() - $visibles->count() - ($miFilaOculta ? 1 : 0);
@endphp

<div class="plica-lista">
    @foreach ($visibles->concat($miFilaOculta ? [$miFila] : []) as $fila)
        @if ($miFilaOculta && $loop->last && $ocultas > 0)
            <div class="plica-salto">···</div>
        @endif
        <div @class(['plica-fila', 'plica-yo' => $socioId && $fila->socio->id === $socioId, 'plica-lider' => $fila->puesto === 1])>
            <div @class(['plica-pos', 'p'.$fila->puesto => $fila->puesto <= 3])>{{ $fila->puesto }}º</div>
            <div class="plica-quien">
                <div class="plica-nombre">{{ $fila->socio->nombre }}{{ $socioId && $fila->socio->id === $socioId ? ' · tú' : '' }}</div>
                <div class="plica-detalle">
                    @if ($modo === 'temporada')
                        @php $mayorFila = \App\Services\Scoring::piezaMayorTexto($grupo->criterio, $fila); @endphp
                        {{ $fila->mangas }} {{ $fila->mangas === 1 ? 'manga' : 'mangas' }}{{ $mayorFila !== '' ? ' · mayor '.$mayorFila : '' }}
                    @else
                        {{ \App\Services\Scoring::valorSecundario($grupo->criterio, $fila) }}
                    @endif
                </div>
                <div class="plica-barra"><span style="width: {{ round($valorDe($fila) / $max * 100) }}%"></span></div>
            </div>
            <div class="plica-valor">
                @if ($modo !== 'temporada')
                    {{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}
                @elseif ($grupo->sistema === \App\Models\Seccion::SISTEMA_PUESTOS || ($grupo->puntosParticipacion ?? 0) > 0 || ($grupo->puntosNoAsistencia ?? 0) !== 0)
                    {{-- Puntos «artificiales»: se enseñan como pts, con lo pescado debajo. --}}
                    {{ \App\Services\Scoring::formatPuntos($fila->puntos) }} pts
                    <small>{{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}</small>
                @else
                    {{-- Lo que ordena el ranking (con descartes aplicados), en su unidad. --}}
                    {{ \App\Services\Scoring::valorRanking($grupo->criterio, $fila->puntos) }}
                @endif
            </div>
        </div>
    @endforeach
    @if ($ocultas > 0 && ! $miFilaOculta)
        <div class="plica-salto">y {{ $ocultas }} más</div>
    @endif
</div>
{{-- Siempre hay premio a la pieza mayor: se dice quién y cuánto. --}}
@if (($grupo->piezaMayor ?? null) !== null)
    <div class="plica-mayor">
        🐟 Pieza mayor{{ $modo === 'temporada' ? ' de la temporada' : '' }}:
        <strong>{{ $grupo->piezaMayor->socio->nombre }}</strong> · {{ $grupo->piezaMayor->texto }}{{ $modo === 'temporada' ? ' ('.$grupo->piezaMayor->manga->nombre.')' : '' }}
    </div>
@endif
