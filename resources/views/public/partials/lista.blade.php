{{-- Filas de una clasificación en las páginas públicas (mismo diseño que los paneles:
     medallas en el podio, barra de distancia con el líder).
     Espera: $grupo (calculado por Scoring) · $modo ('manga' | 'temporada') · $limite opcional. --}}
@php
    $limite ??= null;
    $valorDe = fn (object $f): int => $modo === 'temporada'
        ? (int) $f->puntos
        : (int) match ($grupo->criterio) {
            \App\Models\Seccion::CRITERIO_MEDIDA => $f->medida,
            \App\Models\Seccion::CRITERIO_PIEZAS => $f->piezas,
            default => $f->peso,
        };
    $filas = $grupo->filas->values();
    $max = max(1, (int) $filas->max($valorDe));
    $visibles = $limite ? $filas->take($limite) : $filas;
    $ocultas = $filas->count() - $visibles->count();
@endphp

<div class="divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white px-4">
    @foreach ($visibles as $fila)
        <div class="flex min-h-14 items-center gap-3 py-3">
            <div @class([
                'flex size-10 flex-none items-center justify-center rounded-full text-base font-extrabold',
                'bg-amber-400 text-amber-950 ring-4 ring-amber-300/40' => $fila->puesto === 1,
                'bg-zinc-300 text-zinc-900' => $fila->puesto === 2,
                'bg-amber-600 text-white' => $fila->puesto === 3,
                'bg-slate-100 text-slate-700' => $fila->puesto > 3,
            ])>{{ $fila->puesto }}º</div>
            <div class="min-w-0 flex-1">
                <div @class(['truncate font-semibold', 'text-lg' => $fila->puesto === 1, 'text-base' => $fila->puesto !== 1])>{{ $fila->socio->nombre }}@if ($fila->baja ?? false) <span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wide text-slate-700">Baja</span>@endif</div>
                <div class="text-sm text-slate-500">
                    @if ($modo === 'temporada')
                        @php $mayorFila = \App\Services\Scoring::piezaMayorTexto($grupo->criterio, $fila); @endphp
                        {{ $fila->mangas }} {{ $fila->mangas === 1 ? 'manga' : 'mangas' }}{{ $mayorFila !== '' ? ' · mayor '.$mayorFila : '' }}
                    @else
                        {{ \App\Services\Scoring::valorSecundario($grupo->criterio, $fila) }}
                    @endif
                </div>
                <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-slate-100">
                    <div @class(['h-full rounded-full bg-emerald-500', 'opacity-60' => $fila->puesto !== 1])
                         style="width: {{ round($valorDe($fila) / $max * 100) }}%"></div>
                </div>
            </div>
            <div class="text-right text-base font-bold tabular-nums">
                @if ($modo !== 'temporada')
                    {{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}
                @elseif ($grupo->sistema === \App\Models\Seccion::SISTEMA_PUESTOS || ($grupo->puntosParticipacion ?? 0) > 0 || ($grupo->puntosNoAsistencia ?? 0) !== 0)
                    {{ \App\Services\Scoring::formatPuntos($fila->puntos) }} pts
                    <div class="text-xs font-medium text-slate-500">{{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}</div>
                @else
                    {{ \App\Services\Scoring::valorRanking($grupo->criterio, $fila->puntos) }}
                @endif
            </div>
        </div>
    @endforeach
    @if ($ocultas > 0)
        <div class="py-2 pl-13 text-sm text-slate-500">y {{ $ocultas }} más</div>
    @endif
</div>
@if (($grupo->piezaMayor ?? null) !== null)
    <div class="mt-3 rounded-xl bg-amber-50 px-4 py-2.5 text-sm text-amber-800">
        🐟 Pieza mayor{{ $modo === 'temporada' ? ' de la temporada' : '' }}:
        <strong>{{ $grupo->piezaMayor->socio->nombre }}</strong> · {{ $grupo->piezaMayor->texto }}{{ $modo === 'temporada' ? ' ('.$grupo->piezaMayor->manga->nombre.')' : '' }}
    </div>
@endif
