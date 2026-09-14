{{-- Cuadro manga a manga en la página pública de una sección (misma lógica que
     el del panel: pescadores en filas, mangas en columnas). Espera: $cuadro, $club. --}}
@php
    $conPuntos = $cuadro->sistema === \App\Models\Seccion::SISTEMA_PUESTOS || $cuadro->puntosParticipacion > 0 || ($cuadro->puntosNoAsistencia ?? 0) !== 0;
    $unidad = match ($cuadro->criterio) {
        \App\Models\Seccion::CRITERIO_MEDIDA => 'cm',
        \App\Models\Seccion::CRITERIO_PIEZAS => 'piezas',
        default => 'kg',
    };
    $partir = fn (string $texto): array => array_pad(explode(' ', $texto, 2), 2, '');
    $abreviar = function (string $nombre): string {
        $partes = preg_split('/\s+/u', trim($nombre)) ?: [$nombre];

        return $partes[0].(isset($partes[1]) ? ' '.mb_substr($partes[1], 0, 1).'.' : '');
    };
@endphp

<style>
    .pc { border-collapse: separate; border-spacing: 0; min-width: 100%; font-variant-numeric: tabular-nums; font-size: .9rem; }
    .pc th, .pc td { padding: .5rem .6rem; text-align: right; white-space: nowrap; border-bottom: 1px solid rgb(241 245 249); vertical-align: middle; }
    /* Pescador, total, piezas y mayor, al ancho justo de su contenido: el aire sobrante se lo llevan las mangas. */
    .pc th:first-child, .pc td:first-child, .pc th.total, .pc td.total, .pc th.piezas, .pc td.piezas { width: 1%; }
    .pc thead th { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: rgb(100 116 139); font-weight: 700; vertical-align: bottom; background: rgb(248 250 252); border-bottom: 1px solid rgb(226 232 240); }
    .pc thead th small { display: block; font-size: .7rem; font-weight: 500; text-transform: none; letter-spacing: 0; }
    .pc th:first-child, .pc td:first-child { text-align: left; position: sticky; left: 0; z-index: 2; background: #fff; box-shadow: 6px 0 8px -6px rgba(0,0,0,.12); }
    .pc thead th:first-child { background: rgb(248 250 252); }
    .pc th.total, .pc td.total { position: sticky; right: 0; z-index: 2; background: #fff; box-shadow: -6px 0 8px -6px rgba(0,0,0,.12); font-weight: 800; padding-left: .9rem; }
    .pc thead th.total { background: rgb(248 250 252); }
    .pc tbody tr:last-child td { border-bottom: 0; }
    .pc .quien { font-weight: 600; color: rgb(15 23 42); }
    .pc .pos { flex: none; width: 1.5rem; height: 1.5rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; color: rgb(71 85 105); }
    .pc tr.corte td { padding: .35rem .7rem; font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: rgb(100 116 139); background: rgb(248 250 252); text-align: left; position: static; box-shadow: none; }
    .pc .pos.p1 { background: #fbbf24; color: #451a03; }
    .pc .pos.p2 { background: #d4d4d8; color: #27272a; }
    .pc .pos.p3 { background: #d97706; color: #fff; }
    .pc .celda { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.25; }
    .pc .celda .m { font-size: .7rem; color: rgb(100 116 139); }
    .pc .celda .pm { display: inline-flex; min-width: 1.3rem; height: 1.1rem; padding: 0 .3rem; border-radius: 999px; align-items: center; justify-content: center; font-weight: 700; background: rgb(226 232 240); color: rgb(30 41 59); }
.pc .celda.gana .pm { background: #fbbf24; color: #451a03; }
/* Federación: los puntos de la manga arriba (en oro si ganó la manga) y lo pescado debajo. */
.pc .celda .v.pts { font-size: 1.05rem; }
.pc .celda.gana .v.pts { background: #fbbf24; color: #451a03; border-radius: 999px; padding: 0 .5rem; }
.pc .celda .m .peso { color: rgb(71 85 105); }
.pc .celda.mayor .v { color: rgb(5 150 105); }
.pc .celda .pez { font-size: .8rem; line-height: 1; }
    .pc .celda.descartada .v { text-decoration: line-through; opacity: .45; }
    .pc td.ausente { color: rgb(148 163 184); }
    .pc td.ausente.descartada { text-decoration: line-through; }
    .pc .n.corto { display: none; }
    @media (max-width: 640px) {
        .pc { font-size: .9rem; }
        .pc th, .pc td { padding: .45rem .4rem; }
        .pc th:first-child, .pc td:first-child { max-width: 8rem; padding-left: 1rem; }
        .pc th.total, .pc td.total { padding-right: 1rem; }
        .pc .n.largo { display: none; }
        .pc .n.corto { display: inline; }
        .pc .u, .pc .extra, .pc .piezas { display: none; }
    }
</style>

@if ($cuadro->piezaMayor && ! ($sinPiezaMayor ?? false))
    <div class="mb-3 rounded-xl bg-amber-50 px-4 py-2.5 text-sm text-amber-800">🐟 Pieza mayor de la temporada: <strong>{{ $cuadro->piezaMayor->socio->nombre }}</strong> · {{ $cuadro->piezaMayor->texto }} ({{ $cuadro->piezaMayor->manga->nombre }})</div>
@endif
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="overflow-x-auto">
        <table class="pc">
            <thead>
                <tr>
                    <th>Pescador</th>
                    @foreach ($cuadro->mangas as $manga)
                        <th>
                            <a href="{{ route('club.manga', ['club' => $club->slug, 'manga' => $manga->id]) }}" class="hover:text-emerald-700">{{ $manga->nombre }}</a>
                            <small>{{ $manga->fecha->format('d/m') }}</small>
                        </th>
                    @endforeach
                    <th class="total">Total<small>{{ $conPuntos ? 'puntos' : $unidad }}</small></th>
                    @if ($cuadro->criterio !== \App\Models\Seccion::CRITERIO_PIEZAS)
                        <th class="piezas">Piezas</th>
                    @endif
                    <th class="piezas">Mayor</th>
                </tr>
            </thead>
            <tbody>
                @php
                    // Franja de corte: a partir de aquí, los socios de la sección que no han pescado ninguna manga.
                    $primeroSinMangas = null;
                    foreach ($cuadro->filas->values() as $i => $f) {
                        if ($f->mangas === 0) { $primeroSinMangas ??= $i; } else { $primeroSinMangas = null; }
                    }
                    $columnas = 2 + $cuadro->mangas->count() + ($cuadro->criterio !== \App\Models\Seccion::CRITERIO_PIEZAS ? 1 : 0) + 1;
                @endphp
                @foreach ($cuadro->filas as $fila)
                    @if ($loop->index === $primeroSinMangas && $loop->index > 0)
                        <tr class="corte"><td colspan="{{ $columnas }}">Sin ninguna manga esta temporada</td></tr>
                    @endif
<tr>
                        <td>
                            <span class="inline-flex max-w-full items-center gap-2 font-semibold">
                                <span @class(['pos', 'p'.$fila->puesto => $fila->puesto <= 3])>{{ $fila->puesto }}</span>
                                <span class="n largo truncate">{{ $fila->socio->nombre }}@if ($fila->baja ?? false) <span class="ml-1 rounded bg-slate-200 px-1 text-[10px] font-bold uppercase text-slate-700">Baja</span>@endif</span>
                                <span class="n corto truncate" title="{{ $fila->socio->nombre }}">{{ $abreviar($fila->socio->nombre) }}</span>
                            </span>
                        </td>
                        @foreach ($cuadro->mangas as $manga)
                            @php $c = $fila->celdas[$manga->id] ?? null; @endphp
                            @if ($c === null)
                                @if ($cuadro->sistema === \App\Models\Seccion::SISTEMA_PUESTOS)
                                @php $d = in_array($manga->id, $fila->descartadas ?? [], true); @endphp<td @class(['ausente', 'descartada' => $d]) title="No participó: {{ $cuadro->ausentePorManga[$manga->id] ?? '' }} pts{{ $d ? ' · manga descartada' : '' }}">—<br><small>@if ($d)<s>{{ $cuadro->ausentePorManga[$manga->id] ?? '' }}</s>@else{{ $cuadro->ausentePorManga[$manga->id] ?? '' }}@endif</small></td>
                            @elseif (($cuadro->puntosNoAsistencia ?? 0) !== 0)
                                @php $d = in_array($manga->id, $fila->descartadas ?? [], true); @endphp<td @class(['ausente', 'descartada' => $d]) title="No participó: {{ $cuadro->puntosNoAsistencia > 0 ? '+' : '' }}{{ $cuadro->puntosNoAsistencia }} pts{{ $d ? ' · manga descartada' : '' }}">—<br><small>{{ $cuadro->puntosNoAsistencia > 0 ? '+' : '' }}{{ $cuadro->puntosNoAsistencia }}</small></td>
                            @else
                                @php $d = in_array($manga->id, $fila->descartadas ?? [], true); @endphp<td @class(['ausente', 'descartada' => $d]) title="No participó{{ $d ? ' · manga descartada' : '' }}">—</td>
                            @endif
                            @else
                                @php [$numero, $uni] = $partir($c->texto); @endphp
                                <td>
<div @class(['celda', 'gana' => $c->puesto === 1, 'mayor' => $c->mayorDeLaManga, 'descartada' => $c->descartada]) title="{{ $cuadro->sistema === \App\Models\Seccion::SISTEMA_PUESTOS && $c->puntos !== null ? \App\Services\Scoring::formatPuntos($c->puntos).' pts · ' : '' }}{{ $c->texto }} · {{ $c->puesto }}º{{ $c->mayorDeLaManga ? ' · pieza mayor de la manga ('.$c->mayor.')' : '' }}">
                                        @if ($cuadro->sistema === \App\Models\Seccion::SISTEMA_PUESTOS && $c->puntos !== null)
                                            {{-- Federación: los puntos de la manga arriba; lo pescado, debajo en pequeño. --}}
                                            <span class="v pts font-semibold">{{ \App\Services\Scoring::formatPuntos($c->puntos) }}<span class="u"> pts</span></span>
                                            <span class="m">
                                                <span class="peso">{{ $c->valor > 0 ? $numero.($uni !== '' ? ' '.$uni : '') : 'bolo' }}</span>
                                                @if ($c->mayorDeLaManga)
                                                    <span class="pez" title="Pieza mayor de la manga: {{ $c->mayor }}">🐟</span>
                                                @endif
                                                @if ($cuadro->criterio !== \App\Models\Seccion::CRITERIO_PIEZAS && $c->piezas > 0)
                                                    <span class="extra">{{ $c->piezas }} {{ $c->piezas === 1 ? 'pieza' : 'piezas' }}</span>
                                                @endif
                                                @if ($c->descartada)
                                                    <span class="extra">· descarte</span>
                                                @endif
                                            </span>
                                        @else
                                            <span class="v font-semibold">{{ $numero }}@if ($uni !== '')<span class="u"> {{ $uni }}</span>@endif</span>
                                            <span class="m">
                                                <span class="pm">{{ $c->puesto }}º</span>
                                                @if ($c->mayorDeLaManga)
                                                    <span class="pez" title="Pieza mayor de la manga: {{ $c->mayor }}">🐟</span>
                                                @endif
                                                @if ($cuadro->criterio !== \App\Models\Seccion::CRITERIO_PIEZAS && $c->piezas > 0)
                                                    <span class="extra">{{ $c->piezas }} {{ $c->piezas === 1 ? 'pieza' : 'piezas' }}</span>
                                                @endif
                                                @if ($c->descartada)
                                                    <span class="extra">· descarte</span>
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        @endforeach
                        <td class="total">
                            @if ($conPuntos)
                                {{ \App\Services\Scoring::formatPuntos($fila->puntos) }}<span class="u"> pts</span>
                            @else
                                @php [$numero, $uni] = $partir(\App\Services\Scoring::valorRanking($cuadro->criterio, $fila->puntos)); @endphp
                                {{ $numero }}@if ($uni !== '')<span class="u"> {{ $uni }}</span>@endif
                            @endif
                        </td>
                        @if ($cuadro->criterio !== \App\Models\Seccion::CRITERIO_PIEZAS)
                            <td class="piezas">{{ $fila->piezas }}</td>
                        @endif
                        <td class="piezas">{{ \App\Services\Scoring::piezaMayorTexto($cuadro->criterio, $fila) ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="flex flex-wrap gap-x-4 gap-y-1 px-4 py-3 text-xs text-slate-500">
<span><span class="mr-1 inline-block size-3 rounded-full bg-amber-400 align-middle"></span>1º: ganador de la manga</span>
<span>🐟 <span class="font-bold text-emerald-600">verde</span>: pieza mayor de la manga</span>
        @if ((int) $cuadro->seccion->descartes > 0)
            <span><s>Tachado</s>: manga descartada</span>
        @endif
        <span>—: no participó{{ ($cuadro->puntosNoAsistencia ?? 0) !== 0 ? " (".($cuadro->puntosNoAsistencia > 0 ? "+" : "").$cuadro->puntosNoAsistencia." pts)" : "" }}</span>
        <span>Valores en {{ $unidad }}</span>
    </div>
</div>
