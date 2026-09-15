{{-- Filas de una clasificación en las páginas públicas: puesto, pescador, dato principal.
     Sobria: sin barras ni nombres grandes; el podio lleva un pequeño distintivo de color.
     Espera: $grupo (calculado por Scoring) · $modo ('manga' | 'temporada') · $limite opcional. --}}
@php
    $limite ??= null;
    $socioId ??= null;
    $filas = $grupo->filas->values();
    $visibles = $limite ? $filas->take($limite) : $filas;
    $ocultas = $filas->count() - $visibles->count();
    $conPuntos = $modo === 'temporada' && ($grupo->sistema === \App\Models\Seccion::SISTEMA_PUESTOS || ($grupo->puntosParticipacion ?? 0) > 0 || ($grupo->puntosNoAsistencia ?? 0) !== 0);
@endphp

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <table class="w-full text-sm tabular-nums">
        <thead>
            <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                <th class="w-12 px-3 py-2 text-left">Pos.</th>
                <th class="px-2 py-2 text-left">Pescador</th>
                <th class="px-3 py-2 text-right">{{ $modo === 'temporada' ? ($conPuntos ? 'Puntos' : 'Total') : match ($grupo->criterio) { \App\Models\Seccion::CRITERIO_MEDIDA => 'Medida', \App\Models\Seccion::CRITERIO_PIEZAS => 'Piezas', default => 'Peso' } }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($visibles as $fila)
                <tr @class(['bg-emerald-50' => $fila->participante->incluye($socioId)])>
                    <td class="px-3 py-2.5">
                        <span @class([
                            'inline-flex size-6 items-center justify-center rounded-full text-xs font-bold',
                            'bg-amber-400 text-amber-950' => $fila->puesto === 1,
                            'bg-zinc-300 text-zinc-900' => $fila->puesto === 2,
                            'bg-amber-600 text-white' => $fila->puesto === 3,
                            'text-slate-600' => $fila->puesto > 3,
                        ])>{{ $fila->puesto }}</span>
                    </td>
                    <td class="min-w-0 px-2 py-2.5">
                        <div class="font-semibold text-slate-900">{!! implode('<br>', array_map('e', $fila->participante->lineas())) !!}{{ $fila->participante->incluye($socioId) ? ' · tú' : '' }}@if ($fila->baja ?? false) <span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wide text-slate-700">Baja</span>@endif</div>
                        <div class="text-xs text-slate-500">
                            @if ($modo === 'temporada')
                                @php $mayorFila = \App\Services\Scoring::piezaMayorTexto($grupo->criterio, $fila); @endphp
                                {{ $fila->mangas }} {{ $fila->mangas === 1 ? 'manga' : 'mangas' }}{{ $mayorFila !== '' ? ' · mayor '.$mayorFila : '' }}
                            @else
                                {{ \App\Services\Scoring::valorSecundario($grupo->criterio, $fila) }}
                            @endif
                        </div>
                    </td>
                    <td class="px-3 py-2.5 text-right font-bold text-slate-900">
                        @if ($modo !== 'temporada')
                            {{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}
                        @elseif ($conPuntos)
                            {{ \App\Services\Scoring::pts($fila->puntos) }}
                            <div class="text-xs font-medium text-slate-500">{{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}</div>
                        @else
                            {{ \App\Services\Scoring::valorRanking($grupo->criterio, $fila->puntos) }}
                        @endif
                    </td>
                </tr>
            @endforeach
            @if ($ocultas > 0)
                <tr><td colspan="3" class="px-3 py-2 text-xs text-slate-500">y {{ $ocultas }} más</td></tr>
            @endif
        </tbody>
    </table>
</div>
@if (($grupo->piezaMayor ?? null) !== null && ! ($sinPiezaMayor ?? false))
    <div class="mt-2 text-sm text-slate-600">
        🐟 Pieza mayor{{ $modo === 'temporada' ? ' de la temporada' : '' }}:
        <strong class="text-slate-900">{{ $grupo->piezaMayor->socio->nombre }}</strong> · {{ $grupo->piezaMayor->texto }}{{ $modo === 'temporada' ? ' ('.$grupo->piezaMayor->manga->nombre.')' : '' }}
    </div>
@endif
