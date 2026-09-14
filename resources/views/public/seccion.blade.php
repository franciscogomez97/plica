@extends('layouts.public')

@section('title', 'Ranking '.$seccion->nombre.' · '.$club->nombre)

@section('meta')
    <meta property="og:title" content="Ranking {{ $seccion->nombre }} · {{ $club->nombre }}">
    <meta property="og:description" content="{{ $grupo ? \App\Services\Compartir::resumen($grupo) : 'Todavía sin mangas celebradas.' }}{{ $temporada ? ' · '.$temporada->nombre : '' }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:type" content="website">
    @if ($club->logoUrl())
        <meta property="og:image" content="{{ $club->logoUrl() }}">
    @endif
    <meta name="twitter:card" content="summary">
@endsection

@section('content')
    {{-- Cabecera sobria: club, título, temporada. Las reglas, plegadas: están ahí, no estorban. --}}
    <section class="pb-5">
        <div class="flex items-center gap-2">
            @if ($club->logoUrl())
                <img src="{{ $club->logoUrl() }}" alt="" class="size-6 flex-none rounded-md object-contain">
            @endif
            @if ($club->perfil_publico)
                <a href="{{ route('club.publico', $club) }}" class="text-sm text-slate-500 hover:text-emerald-700">← {{ $club->nombre }}</a>
            @else
                <span class="text-sm text-slate-500">{{ $club->nombre }}</span>
            @endif
        </div>
        <div class="mt-2 flex items-center justify-between gap-4">
            <h1 class="min-w-0 text-2xl font-extrabold tracking-tight text-slate-900">Ranking {{ $seccion->nombre }}</h1>
            @if ($texto)
                @include('partials.compartir', ['titulo' => 'Ranking '.$seccion->nombre.' · '.$club->nombre, 'texto' => $texto, 'url' => $url, 'compacto' => true])
            @endif
        </div>
        <p class="mt-1 text-sm text-slate-500">
            {{ $temporada?->nombre ?? 'Sin temporada activa' }}@if ($cuadro && $cuadro->mangas->isNotEmpty()) · {{ $cuadro->mangas->count() === 1 ? '1 manga celebrada' : $cuadro->mangas->count().' mangas celebradas' }}@endif
        </p>
        <details class="mt-3 text-sm text-slate-500">
            <summary class="cursor-pointer select-none font-medium text-slate-700 hover:text-emerald-700">Cómo puntúa esta sección</summary>
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
            <h2 class="mb-2 text-base font-bold text-slate-900">Clasificación general</h2>
            @include('public.partials.cuadro', ['cuadro' => $cuadro, 'club' => $club, 'sinPiezaMayor' => true])
        </section>

        @if ($clasifUltima)
            <section class="pb-8">
                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 class="text-base font-bold text-slate-900">Última manga: {{ $ultimaManga->nombre }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ $ultimaManga->fecha->format('d/m/Y') }}{{ $ultimaManga->lugar ? ' · '.$ultimaManga->lugar : '' }}
                        · <a href="{{ route('club.manga', ['club' => $club->slug, 'manga' => $ultimaManga->id]) }}" class="font-medium text-emerald-700 hover:underline">Ver y compartir</a>
                    </p>
                </div>
                @include('public.partials.lista', ['grupo' => $clasifUltima, 'modo' => 'manga'])
            </section>
        @endif
    @endif
@endsection
