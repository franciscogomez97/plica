{{-- El ranking de una sección: lo ve el público en /c/{club}/{seccion} y el admin
     en su panel, con este MISMO parcial (los datos los calcula RankingDeSeccion).
     Espera: $club, $seccion, $temporada, $grupo, $cuadro, $ultimaManga, $clasifUltima,
     $url, $texto. Opcionales: $urlManga (closure Manga → URL de su clasificación;
     por defecto la pública) y $enPanel (añade el enlace a la página pública). --}}
@php
    $enPanel ??= false;
    $urlManga ??= fn ($manga) => route('club.manga', ['club' => $club->slug, 'manga' => $manga->id]);
@endphp
    {{-- Cabecera: escudo y nombre del club grandes, título y temporada. Las reglas, plegadas. --}}
    <section class="pb-5">
        <div class="flex items-center gap-3">
            @if ($club->logoUrl())
                <img src="{{ $club->logoUrl() }}" alt="" class="size-12 flex-none rounded-lg object-contain">
            @endif
            <span class="text-xl font-bold text-slate-900">{{ $club->nombre }}</span>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <h1 class="min-w-0 text-2xl font-extrabold tracking-tight text-slate-900">Ranking {{ $seccion->nombre }}</h1>
            @if ($texto)
                <div class="flex flex-wrap items-center gap-2">
                    @include('partials.compartir', ['titulo' => 'Ranking '.$seccion->nombre.' · '.$club->nombre, 'texto' => $texto, 'url' => $url, 'compacto' => true])
                    @include('partials.compartir-imagen', ['titulo' => 'Ranking '.$seccion->nombre.' · '.$club->nombre, 'url' => \App\Services\Podio::urlRanking($temporada, $seccion, $grupo), 'compacto' => true, 'pie' => $texto."\n".$url])
                    @if ($enPanel)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="text-sm text-slate-500 underline hover:text-emerald-800">Ver la página pública</a>
                    @endif
                </div>
            @endif
        </div>
        <p class="mt-1 text-sm text-slate-500">
            {{ $temporada?->nombre ?? 'Sin temporada activa' }}@if ($cuadro && $cuadro->mangas->isNotEmpty()) · {{ $cuadro->mangas->count() === 1 ? '1 manga celebrada' : $cuadro->mangas->count().' mangas celebradas' }}@endif
        </p>
        <details class="mt-3 text-sm text-slate-500">
            <summary class="cursor-pointer select-none font-medium text-slate-700 hover:text-emerald-800">Cómo puntúa esta sección</summary>
            <p class="mt-1 max-w-3xl">{{ $seccion->resumenReglas() }}</p>
        </details>
    </section>

    @if ($grupo === null || $cuadro === null || $cuadro->mangas->isEmpty())
        <section class="pb-8">
            <div class="rounded-xl border border-slate-200 bg-white p-5 text-slate-500">
                Todavía no hay mangas celebradas de esta sección. En cuanto se cierre la primera, aquí saldrá el ranking.
            </div>
        </section>
    @else
        @php
            $lider = $grupo->filas->first();
            $conPuntos = $grupo->sistema === \App\Models\Seccion::SISTEMA_PUESTOS || ($grupo->puntosParticipacion ?? 0) > 0 || ($grupo->puntosNoAsistencia ?? 0) !== 0;
            $liderValor = $conPuntos ? \App\Services\Scoring::formatPuntos($lider->puntos).' pts' : \App\Services\Scoring::valorRanking($grupo->criterio, $lider->puntos);
            $liderDetalle = $conPuntos ? \App\Services\Scoring::valorPrincipal($grupo->criterio, $lider) : ($lider->mangas === 1 ? '1 manga' : $lider->mangas.' mangas');
        @endphp

        {{-- Las dos tarjetas de cabecera: quién va primero y la pieza mayor del año. --}}
        <section class="pb-6">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Líder</div>
                    <div class="mt-1 flex items-baseline justify-between gap-3">
                        <div class="min-w-0 truncate text-base font-bold text-slate-900">{{ $lider->socio->nombre }}</div>
                        <div class="flex-none text-right">
                            <div class="text-base font-bold tabular-nums text-slate-900">{{ $liderValor }}</div>
                            <div class="text-xs text-slate-500">{{ $liderDetalle }}</div>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pieza mayor de la temporada</div>
                    @if ($grupo->piezaMayor)
                        <div class="mt-1 flex items-baseline justify-between gap-3">
                            <div class="min-w-0 truncate text-base font-bold text-slate-900">{{ $grupo->piezaMayor->socio->nombre }}</div>
                            <div class="flex-none text-right">
                                <div class="text-base font-bold tabular-nums text-emerald-700">{{ $grupo->piezaMayor->texto }}</div>
                                <div class="text-xs text-slate-500">{{ $grupo->piezaMayor->manga->nombre }}</div>
                            </div>
                        </div>
                    @else
                        <div class="mt-1 text-sm text-slate-500">Todavía sin piezas</div>
                    @endif
                </div>
            </div>
        </section>

        {{-- La clasificación es el cuadro: puesto, pescador, una columna por manga y el total. --}}
        <section class="pb-8">
            @php
                $unidad = match ($cuadro->criterio) {
                    \App\Models\Seccion::CRITERIO_MEDIDA => 'cm',
                    \App\Models\Seccion::CRITERIO_PIEZAS => 'piezas',
                    default => 'kg',
                };
            @endphp
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 class="text-base font-bold text-slate-900">Clasificación general</h2>
                {{-- La leyenda del cuadro, en pequeño, junto al título. --}}
                <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-slate-500">
                    <span><span class="mr-1 inline-block size-2.5 rounded-full bg-amber-400 align-middle"></span>ganador de la manga</span>
                    <span>🐟 <span class="font-semibold text-emerald-700">verde</span>: pieza mayor de la manga</span>
                    @if ((int) $cuadro->seccion->descartes > 0)
                        <span><s>tachado</s>: manga descartada</span>
                    @endif
                </div>
            </div>
            @include('public.partials.cuadro', ['cuadro' => $cuadro, 'club' => $club, 'sinPiezaMayor' => true, 'urlManga' => $urlManga])
        </section>

        @if ($clasifUltima)
            <section class="pb-8">
                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 class="text-base font-bold text-slate-900">Última manga: {{ $ultimaManga->nombre }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ $ultimaManga->fecha->format('d/m/Y') }}{{ $ultimaManga->lugar ? ' · '.$ultimaManga->lugar : '' }}
                        · <a href="{{ $urlManga($ultimaManga) }}" class="font-medium text-emerald-700 hover:underline">Ver y compartir</a>
                    </p>
                </div>
                @include('public.partials.lista', ['grupo' => $clasifUltima, 'modo' => 'manga'])
            </section>
        @endif
    @endif
