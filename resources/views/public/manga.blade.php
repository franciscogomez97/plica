@extends('layouts.public')

@section('title', $manga->nombre.' · '.$club->nombre)

@section('meta')
    <meta property="og:title" content="{{ $manga->nombre }} · {{ $club->nombre }}">
    <meta property="og:description" content="{{ $grupos->map(fn ($g) => $g->nombre.': '.\App\Services\Compartir::resumen($g))->implode(' — ') ?: 'Clasificación de la manga.' }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:type" content="website">
    @if ($grupos->isNotEmpty())
        {{-- La vista previa del enlace en WhatsApp es la tarjeta del podio. --}}
        <meta property="og:image" content="{{ \App\Services\Podio::urlManga($manga, $grupos->first()) }}">
        <meta property="og:image:width" content="{{ \App\Services\Podio::ANCHO }}">
        <meta property="og:image:height" content="{{ \App\Services\Podio::ALTO }}">
        <meta name="twitter:card" content="summary_large_image">
    @elseif ($club->logoUrl())
        <meta property="og:image" content="{{ $club->logoUrl() }}">
        <meta name="twitter:card" content="summary">
    @endif
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
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">🎣 {{ $manga->nombre }}</h1>
        <p class="mt-1 text-base text-slate-500">
            {{ $manga->fecha->format('d/m/Y') }}{{ $manga->horarioCorto() ? ' · '.$manga->horarioCorto() : '' }}{{ $manga->lugar ? ' · '.$manga->lugar : '' }}{{ $manga->seccion ? ' · '.$manga->seccion->nombre : '' }}
            @if ($manga->estado !== \App\Models\Manga::ESTADO_CELEBRADA && $grupos->isNotEmpty())
                · <span class="text-amber-600">clasificación provisional</span>
            @endif
        </p>
        @if ($grupos->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center gap-2">
                @include('partials.compartir', ['titulo' => $manga->nombre.' · '.$club->nombre, 'texto' => $texto, 'url' => $url])
                @if ($grupos->count() === 1)
                    @include('partials.compartir-imagen', ['titulo' => $manga->nombre.' · '.$club->nombre, 'url' => \App\Services\Podio::urlManga($manga, $grupos->first()), 'pie' => $texto."\n".$url])
                @else
                    {{-- Manga de club con varias secciones: una tarjeta por sección. --}}
                    @foreach ($grupos as $g)
                        @include('partials.compartir-imagen', ['titulo' => $manga->nombre.' · '.$g->nombre.' · '.$club->nombre, 'url' => \App\Services\Podio::urlManga($manga, $g), 'etiqueta' => 'Imagen '.$g->nombre, 'pie' => $texto."\n".$url])
                    @endforeach
                @endif
            </div>
        @endif
    </section>

    @if ($manga->estado === \App\Models\Manga::ESTADO_PROGRAMADA)
        {{-- Convocatoria: los socios dicen si irán. Es intención: la asistencia real la pasa el admin. --}}
        <section class="pb-8">
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <h2 class="text-lg font-bold text-emerald-600">📅 {{ ucfirst($manga->fecha->locale('es')->isoFormat('dddd D [de] MMMM')) }}{{ $manga->horario() ? ' · '.$manga->horario() : '' }}</h2>
                <p class="mt-1 text-base text-slate-700">
                    {{ $manga->lugar ?? 'Lugar por confirmar' }}
                    @if ($manga->ubicacion_url)
                        · <a href="{{ $manga->ubicacion_url }}" target="_blank" rel="noopener" class="font-semibold text-emerald-600 hover:underline">📍 Cómo llegar</a>
                    @endif
                </p>
                @if ($manga->quedada())
                    {{-- Dónde se junta el club antes de ir al agua. --}}
                    <p class="mt-2 text-base text-slate-700">
                        🤝 Quedada previa {{ $manga->quedada() }}
                        @if ($manga->quedada_url)
                            · <a href="{{ $manga->quedada_url }}" target="_blank" rel="noopener" class="font-semibold text-emerald-600 hover:underline">📍 Cómo llegar</a>
                        @endif
                    </p>
                @endif

                @if (session('asistencia'))
                    <p class="mt-3 rounded-lg bg-emerald-100 px-3 py-2 text-sm font-semibold text-emerald-700">{{ session('asistencia') }}</p>
                @endif

                <p class="mt-4 text-sm text-slate-500">
                    <strong class="text-slate-800">{{ $confirmados->count() === 1 ? '1 confirmado' : $confirmados->count().' confirmados' }}</strong>{{ $confirmados->isNotEmpty() ? ': '.$confirmados->implode(', ') : '. Sé el primero.' }}
                </p>

                <div class="mt-4">
                    @if ($socio)
                        <form method="post" action="{{ route('club.manga.asistire', ['club' => $club->slug, 'manga' => $manga->id]) }}">
                            @csrf
                            <button type="submit" @class([
                                'inline-flex min-h-11 items-center gap-2 rounded-xl px-5 py-2.5 text-base font-bold',
                                'bg-emerald-600 text-white hover:bg-emerald-500' => $voy,
                                'border border-emerald-500 text-emerald-600 hover:bg-emerald-50' => ! $voy,
                            ])>{{ $voy ? '✓ Asistiré · toca para cancelar' : 'Asistiré' }}</button>
                        </form>
                    @elseif (auth()->check())
                        <p class="text-sm text-slate-500">Solo los socios del club pueden confirmar asistencia.</p>
                    @else
                        <a href="/app" class="inline-flex min-h-11 items-center rounded-xl bg-emerald-600 px-5 py-2.5 text-base font-bold text-white hover:bg-emerald-500">¿Vas a ir? Entra y confirma</a>
                        <p class="mt-2 text-sm text-slate-500">¿Sin cuenta todavía? Pídele tu enlace de acceso al admin del club.</p>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @if ($grupos->isEmpty() && $manga->estado !== \App\Models\Manga::ESTADO_PROGRAMADA)
        <section class="pb-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 text-slate-500">Todavía no hay pesajes apuntados en esta manga.</div>
        </section>
    @endif

    @foreach ($grupos as $grupo)
        <section class="pb-8">
            <h2 class="mb-1 text-lg font-bold text-emerald-600">{{ $grupo->nombre }} · por {{ mb_strtolower(\App\Models\Seccion::CRITERIOS[$grupo->criterio] ?? $grupo->criterio) }}</h2>
            @if ($grupo->seccion)
                <p class="mb-3 text-sm text-slate-500">
                    <a href="{{ route('club.seccion', ['club' => $club->slug, 'seccion' => $grupo->seccion->slug]) }}" class="text-emerald-600 hover:underline">Ver el ranking de {{ $grupo->nombre }} →</a>
                </p>
            @endif
            @include('public.partials.lista', ['grupo' => $grupo, 'modo' => 'manga'])
        </section>
    @endforeach
@endsection
