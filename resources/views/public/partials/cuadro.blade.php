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
    .pc { border-collapse: separate; border-spacing: 0; min-width: 100%; font-variant-numeric: tabular-nums; }
    .pc th, .pc td { padding: .6rem .7rem; text-align: right; white-space: nowrap; border-bottom: 1px solid rgb(30 41 59); vertical-align: middle; }
    .pc thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: rgb(148 163 184); font-weight: 700; vertical-align: bottom; }
    .pc thead th small { display: block; font-size: .72rem; font-weight: 500; text-transform: none; letter-spacing: 0; }
    .pc th:first-child, .pc td:first-child { text-align: left; position: sticky; left: 0; z-index: 2; background: rgb(15 23 42); box-shadow: 6px 0 8px -6px rgba(0,0,0,.6); }
    .pc th.total, .pc td.total { position: sticky; right: 0; z-index: 2; background: rgb(15 23 42); box-shadow: -6px 0 8px -6px rgba(0,0,0,.6); font-weight: 800; }
    .pc tbody tr:last-child td { border-bottom: 0; }
    .pc .pos { flex: none; width: 1.6rem; height: 1.6rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 800; background: rgb(51 65 85); }
    .pc .pos.p1 { background: #fbbf24; color: #451a03; }
    .pc .pos.p2 { background: #d4d4d8; color: #27272a; }
    .pc .pos.p3 { background: #d97706; color: #fff; }
    .pc .celda { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.25; }
    .pc .celda .m { font-size: .7rem; color: rgb(148 163 184); }
    .pc .celda .pm { display: inline-flex; min-width: 1.3rem; height: 1.1rem; padding: 0 .3rem; border-radius: 999px; align-items: center; justify-content: center; font-weight: 700; background: rgb(51 65 85); color: rgb(226 232 240); }
.pc .celda.gana .pm { background: #fbbf24; color: #451a03; }
.pc .celda.mayor .v { color: rgb(52 211 153); }
.pc .celda .pez { font-size: .8rem; line-height: 1; }
    .pc .celda.descartada .v { text-decoration: line-through; opacity: .45; }
    .pc td.ausente { color: rgb(71 85 105); }
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
    <div class="mb-3 rounded-xl bg-amber-400/10 px-4 py-2.5 text-sm text-amber-200">🐟 Pieza mayor de la temporada: <strong>{{ $cuadro->piezaMayor->socio->nombre }}</strong> · {{ $cuadro->piezaMayor->texto }} ({{ $cuadro->piezaMayor->manga->nombre }})</div>
@endif
<div class="rounded-2xl border border-slate-800 bg-slate-900">
    <div class="overflow-x-auto">
        <table class="pc">
            <thead>
                <tr>
                    <th>Pescador</th>
                    @foreach ($cuadro->mangas as $manga)
                        <th>
                            <a href="{{ route('club.manga', ['club' => $club->slug, 'manga' => $manga->id]) }}" class="hover:text-emerald-400">{{ $manga->nombre }}</a>
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
                @foreach ($cuadro->filas as $fila)
<tr>
                        <td>
                            <span class="inline-flex max-w-full items-center gap-2 font-semibold">
                                <span @class(['pos', 'p'.$fila->puesto => $fila->puesto <= 3])>{{ $fila->puesto }}</span>
                                <span class="n largo truncate">{{ $fila->socio->nombre }}</span>
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
<div @class(['celda', 'gana' => $c->puesto === 1, 'mayor' => $c->mayorDeLaManga, 'descartada' => $c->descartada]) title="{{ $c->texto }} · {{ $c->puesto }}º{{ $c->mayorDeLaManga ? ' · pieza mayor de la manga ('.$c->mayor.')' : '' }}">
    <span class="v font-semibold">{{ $numero }}@if ($uni !== '')<span class="u"> {{ $uni }}</span>@endif</span>
    <span class="m">
        <span class="pm">{{ $cuadro->sistema === \App\Models\Seccion::SISTEMA_PUESTOS && $c->puntos !== null ? \App\Services\Scoring::formatPuntos($c->puntos) : $c->puesto.'º' }}</span>
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
<span>🐟 <span class="font-bold text-emerald-400">verde</span>: pieza mayor de la manga</span>
        @if ((int) $cuadro->seccion->descartes > 0)
            <span><s>Tachado</s>: manga descartada</span>
        @endif
        <span>—: no participó{{ ($cuadro->puntosNoAsistencia ?? 0) !== 0 ? " (".($cuadro->puntosNoAsistencia > 0 ? "+" : "").$cuadro->puntosNoAsistencia." pts)" : "" }}</span>
        <span>Valores en {{ $unidad }}</span>
    </div>
</div>
