{{-- Cuadro manga a manga de una sección (admin y socio): pescadores en filas,
     mangas en columnas. Espera: $cuadro (Scoring::cuadroSeccion), $conPuntos, $unidad,
     $conPiezas, $partir, $abreviar y $urlManga (closure Manga → URL de su clasificación). --}}
<style>
    .cuadro-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .cuadro { border-collapse: separate; border-spacing: 0; min-width: 100%; font-variant-numeric: tabular-nums; font-size: .95rem; }
    .cuadro th, .cuadro td { padding: .65rem .8rem; text-align: right; border-bottom: 1px solid rgba(128,128,128,.18); white-space: nowrap; vertical-align: middle; }
    /* Color y no opacity: con opacity, el fondo de las cabeceras fijas se vuelve translúcido y se ve lo que pasa por debajo. */
    .cuadro thead th { font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; font-weight: 700; vertical-align: bottom; border-bottom-width: 2px; }
    .dark .cuadro thead th { color: #9ca3af; }
    .cuadro thead th small { display: block; font-size: .72rem; font-weight: 500; text-transform: none; letter-spacing: 0; opacity: .8; }
    .cuadro thead th a { display: inline-block; max-width: 9rem; overflow: hidden; text-overflow: ellipsis; vertical-align: bottom; padding: .35rem 0; min-height: 1.5rem; } /* objetivo táctil ≥ 24 px */
    .cuadro thead th a:hover { color: rgb(16 185 129); }
    .cuadro tbody tr:last-child td { border-bottom: 0; }

    /* Columnas fijas: el pescador a la izquierda, el total a la derecha. Lo que se desliza son las mangas. */
    .cuadro th:first-child, .cuadro td:first-child { text-align: left; position: sticky; left: 0; z-index: 2; background: #fff; box-shadow: 6px 0 8px -6px rgba(0,0,0,.12); }
    .cuadro th.total, .cuadro td.total { position: sticky; right: 0; z-index: 2; background: #fff; box-shadow: -6px 0 8px -6px rgba(0,0,0,.12); }
    .dark .cuadro th:first-child, .dark .cuadro td:first-child, .dark .cuadro th.total, .dark .cuadro td.total { background: #18181b; }
    .cuadro .quien { display: inline-flex; align-items: center; gap: .6rem; font-weight: 600; max-width: 100%; }
    .cuadro .quien .n { overflow: hidden; text-overflow: ellipsis; }
    .cuadro .quien .n.corto { display: none; }
    .cuadro .pos { flex: none; width: 1.75rem; height: 1.75rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: .74rem; font-weight: 800; background: rgba(128,128,128,.15); }
    .cuadro .pos.p1 { background: #fbbf24; color: #451a03; }
    .cuadro .pos.p2 { background: #d4d4d8; color: #27272a; }
    .cuadro .pos.p3 { background: #d97706; color: #fff; }

    .celda { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.25; }
    .celda .v { font-weight: 600; }
    .celda .m { font-size: .7rem; opacity: .6; display: inline-flex; gap: .3rem; align-items: center; }
    .celda .m .pm { display: inline-flex; min-width: 1.35rem; height: 1.1rem; padding: 0 .3rem; border-radius: 999px; align-items: center; justify-content: center; font-weight: 700; background: rgba(128,128,128,.15); }
/* El oro del «1º» ya dice quién ganó la manga; el verde es para la pieza mayor de esa manga. */
.celda.gana .m .pm { background: #fbbf24; color: #451a03; }
/* Federación: los puntos de la manga arriba (en oro si ganó la manga) y lo pescado debajo. */
.celda .v.pts { font-size: 1.05rem; }
.celda.gana .v.pts { background: #fbbf24; color: #451a03; border-radius: 999px; padding: 0 .5rem; }
.celda .m .peso { font-weight: 500; }
.celda.mayor .v { color: rgb(16 185 129); }
.celda .m .pez { font-size: .8rem; line-height: 1; }
    .celda.descartada .v { text-decoration: line-through; opacity: .45; }
    .celda.descartada .m { opacity: .45; }
    .cuadro td.ausente { opacity: .3; }
    .cuadro td.ausente.descartada { text-decoration: line-through; }
    .cuadro td.total { font-weight: 800; font-size: 1rem; }
    .cuadro td.total small { display: block; font-size: .7rem; font-weight: 500; opacity: .6; }
    .cuadro-leyenda { display: flex; flex-wrap: wrap; gap: .6rem 1rem; margin-top: .9rem; font-size: .78rem; opacity: .7; }
    .cuadro-leyenda span { display: inline-flex; align-items: center; gap: .35rem; }
    .cuadro-leyenda .muestra { width: .9rem; height: .9rem; border-radius: 999px; display: inline-block; flex: none; }
    .cuadro-desliza { display: none; }

    /* Móvil: todo más compacto, nombres abreviados, solo el número en las celdas. */
    @media (max-width: 640px) {
        .cuadro { font-size: .9rem; }
        .cuadro th, .cuadro td { padding: .45rem .4rem; }
        /* La tabla ocupa el ancho completo de la tarjeta: las columnas fijas quedan pegadas a sus bordes. */
        .cuadro-wrap { margin: 0 -1.5rem; }
        .cuadro th:first-child, .cuadro td:first-child { max-width: 8rem; padding-left: 1rem; }
        .cuadro th.total, .cuadro td.total { padding-right: 1rem; }
        .celda .m .pm { min-width: 1.25rem; }
        .cuadro thead th a { max-width: 5.5rem; }
        .cuadro .quien { gap: .4rem; }
        .cuadro .quien .n.largo { display: none; }
        .cuadro .quien .n.corto { display: inline; }
        .cuadro .pos { width: 1.5rem; height: 1.5rem; font-size: .68rem; }
        .celda .u, .celda .m .extra { display: none; }
        .cuadro td.total { font-size: .95rem; }
        .cuadro td.total .u { display: none; }
        .cuadro th.piezas, .cuadro td.piezas { display: none; }
        .cuadro-desliza { display: flex; align-items: center; gap: .35rem; margin-top: .6rem; font-size: .75rem; opacity: .55; }
    }
</style>

@if ($cuadro->piezaMayor)
    <div class="plica-mayor" style="margin:0 0 1rem">🐟 Pieza mayor de la temporada: <strong>{{ $cuadro->piezaMayor->socio->nombre }}</strong> · {{ $cuadro->piezaMayor->texto }} ({{ $cuadro->piezaMayor->manga->nombre }})</div>
@endif
<div class="cuadro-wrap">
    <table class="cuadro">
        <thead>
            <tr>
                <th>Pescador</th>
                @foreach ($cuadro->mangas as $manga)
                    <th>
                        <a href="{{ $urlManga($manga) }}" wire:navigate title="Ver la clasificación de {{ $manga->nombre }}">{{ $manga->nombre }}</a>
                        <small>{{ $manga->fecha->format('d/m') }}</small>
                    </th>
                @endforeach
                <th class="total">Total<small>{{ $conPuntos ? 'puntos' : $unidad }}</small></th>
                @if ($conPiezas)
                    <th class="piezas">Piezas</th>
                @endif
                <th class="piezas">Mayor</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cuadro->filas as $fila)
<tr>
                    <td>
                        <span class="quien">
                            <span @class(['pos', 'p'.$fila->puesto => $fila->puesto <= 3])>{{ $fila->puesto }}</span>
                            <span class="n largo">{!! implode('<br>', array_map('e', $fila->participante->lineas())) !!}@if ($fila->baja ?? false) <span class="plica-baja">Baja</span>@endif</span>
                            <span class="n corto" title="{{ $fila->participante->nombre }}">{{ $fila->participante->nombreCorto() }}</span>
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
<div @class(['celda', 'gana' => $c->puesto === 1, 'mayor' => $c->mayorDeLaManga, 'descartada' => $c->descartada])
                                     title="{{ $cuadro->sistema === \App\Models\Seccion::SISTEMA_PUESTOS && $c->puntos !== null ? \App\Services\Scoring::formatPuntos($c->puntos).' pts · ' : '' }}{{ $c->texto }} · {{ $c->puesto }}º en {{ $manga->nombre }}{{ $c->mayorDeLaManga ? ' · pieza mayor de la manga ('.$c->mayor.')' : '' }}{{ $c->descartada ? ' · manga descartada' : '' }}">
                                    @if ($cuadro->sistema === \App\Models\Seccion::SISTEMA_PUESTOS && $c->puntos !== null)
                                        {{-- Federación: lo que manda son los puntos de la manga (arriba); lo pescado, debajo en pequeño. --}}
                                        <span class="v pts">{{ \App\Services\Scoring::formatPuntos($c->puntos) }}<span class="u"> pts</span></span>
                                        <span class="m">
                                            <span class="peso">{{ $c->valor > 0 ? $numero.($uni !== '' ? ' '.$uni : '') : '0 '.$unidad }}</span>
                                            @if ($c->mayorDeLaManga)
                                                <span class="pez" title="Pieza mayor de la manga: {{ $c->mayor }}">🐟</span>
                                            @endif
                                            @if ($conPiezas && $c->piezas > 0)
                                                <span class="extra">{{ $c->piezas }} {{ $c->piezas === 1 ? 'pieza' : 'piezas' }}</span>
                                            @endif
                                            @if ($c->descartada)
                                                <span class="extra">· descarte</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="v">{{ $numero }}@if ($uni !== '')<span class="u"> {{ $uni }}</span>@endif</span>
                                        <span class="m">
                                            <span class="pm">{{ $c->puesto }}º</span>
                                            @if ($c->mayorDeLaManga)
                                                <span class="pez" title="Pieza mayor de la manga: {{ $c->mayor }}">🐟</span>
                                            @endif
                                            @if ($conPiezas && $c->piezas > 0)
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
                            <small>{{ \App\Services\Scoring::valorPrincipal($cuadro->criterio, $fila) }}</small>
                        @else
                            @php [$numero, $uni] = $partir(\App\Services\Scoring::valorRanking($cuadro->criterio, $fila->puntos)); @endphp
                            {{ $numero }}@if ($uni !== '')<span class="u"> {{ $uni }}</span>@endif
                        @endif
                    </td>
                    @if ($conPiezas)
                        <td class="piezas">{{ $fila->piezas }}</td>
                    @endif
                    <td class="piezas">{{ \App\Services\Scoring::piezaMayorTexto($cuadro->criterio, $fila) ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($cuadro->mangas->count() > 2)
    <div class="cuadro-desliza">
        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowsRightLeft" style="width:1rem; height:1rem" />
        Desliza para ver todas las mangas
    </div>
@endif

<div class="cuadro-leyenda">
<span><span class="muestra" style="background:#fbbf24"></span> 1º: ganador de la manga</span>
<span>🐟 <span style="color:rgb(16 185 129); font-weight:700">verde</span>: pieza mayor de la manga</span>
    @if ((int) $cuadro->seccion->descartes > 0)
        <span><s>Tachado</s>: manga descartada (no cuenta)</span>
    @endif
    <span>—: no participó{{ ($cuadro->puntosNoAsistencia ?? 0) !== 0 ? " (".($cuadro->puntosNoAsistencia > 0 ? "+" : "").$cuadro->puntosNoAsistencia." pts)" : "" }}</span>
    <span>Valores en {{ $unidad }}</span>
</div>
