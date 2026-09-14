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
    <section class="pb-6">
        <div class="flex items-center gap-2">
            @if ($club->logoUrl())
                <img src="{{ $club->logoUrl() }}" alt="" class="size-7 flex-none rounded-lg object-contain">
            @endif
            @if ($club->perfil_publico)
                <a href="{{ route('club.publico', $club) }}" class="text-sm text-slate-500 hover:text-emerald-700">← {{ $club->nombre }}</a>
            @else
                <span class="text-sm text-slate-500">{{ $club->nombre }}</span>
            @endif
        </div>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">🏆 Ranking {{ $seccion->nombre }}</h1>
        <p class="mt-1 text-base text-slate-500">{{ $temporada?->nombre ?? 'Sin temporada activa' }} · {{ $seccion->resumenReglas() }}</p>
        @if ($texto)
            <div class="mt-4">
                @include('partials.compartir', ['titulo' => 'Ranking '.$seccion->nombre.' · '.$club->nombre, 'texto' => $texto, 'url' => $url])
            </div>
        @endif
    </section>

    @if ($grupo === null)
        <section class="pb-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 text-slate-500">
                Todavía no hay mangas celebradas de esta sección. En cuanto se cierre la primera, aquí saldrá el ranking.
            </div>
        </section>
    @else
        <section class="pb-8">
            <h2 class="mb-3 text-lg font-bold text-emerald-600">Clasificación general</h2>
            @include('public.partials.lista', ['grupo' => $grupo, 'modo' => 'temporada'])
        </section>

        <section class="pb-8">
            <h2 class="mb-3 text-lg font-bold text-emerald-600">Manga a manga</h2>
            {{-- La pieza mayor de la temporada ya sale arriba, en la general: aquí no se repite. --}}
            @include('public.partials.cuadro', ['cuadro' => $cuadro, 'club' => $club, 'sinPiezaMayor' => true])
        </section>

        @if ($clasifUltima)
            <section class="pb-8">
                <h2 class="mb-1 text-lg font-bold text-emerald-600">🎣 Última manga: {{ $ultimaManga->nombre }}</h2>
                <p class="mb-3 text-sm text-slate-500">
                    {{ $ultimaManga->fecha->format('d/m/Y') }}{{ $ultimaManga->lugar ? ' · '.$ultimaManga->lugar : '' }}
                    · <a href="{{ route('club.manga', ['club' => $club->slug, 'manga' => $ultimaManga->id]) }}" class="text-emerald-600 hover:underline">Ver y compartir</a>
                </p>
                @include('public.partials.lista', ['grupo' => $clasifUltima, 'modo' => 'manga'])
            </section>
        @endif
    @endif
@endsection
