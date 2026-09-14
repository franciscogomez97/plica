@extends('layouts.public')

@section('title', $club->nombre.' — Plica')

@section('meta')
    <meta property="og:title" content="{{ $club->nombre }}">
    <meta property="og:description" content="Rankings, clasificaciones y próximas mangas de {{ $club->nombre }}.">
    <meta property="og:url" content="{{ route('club.publico', $club) }}">
    <meta property="og:type" content="website">
    @if ($club->logoUrl())
        <meta property="og:image" content="{{ $club->logoUrl() }}">
    @endif
@endsection

@section('content')
    <section class="pb-8">
        <div class="flex items-center gap-4">
            @if ($club->logoUrl())
                <img src="{{ $club->logoUrl() }}" alt="" class="size-16 flex-none rounded-2xl bg-white/5 object-contain p-1">
            @endif
            <h1 class="text-3xl font-extrabold tracking-tight">{{ $club->nombre }}</h1>
        </div>
        <p class="mt-1 text-base text-slate-500">
            {{ $club->localidad }}
            @if ($club->email_contacto) · <a class="text-emerald-600 hover:underline" href="mailto:{{ $club->email_contacto }}">{{ $club->email_contacto }}</a> @endif
            @if ($club->telefono_contacto) · {{ $club->telefono_contacto }} @endif
        </p>
        @if ($club->descripcion)
            <p class="mt-3 max-w-2xl text-base text-slate-700">{{ $club->descripcion }}</p>
        @endif
    </section>

    @if ($proximas->isNotEmpty())
        <section class="pb-8">
            <h2 class="mb-3 text-lg font-bold text-emerald-600">📅 Próximas mangas</h2>
            <div class="divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white px-4">
                @foreach ($proximas as $manga)
                    <a href="{{ $manga->urlPublica() }}" class="flex min-h-14 items-center gap-3 py-3 hover:text-emerald-800">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-base font-semibold">{{ $manga->nombre }}{{ $manga->seccion ? ' · '.$manga->seccion->nombre : '' }}</div>
                            <div class="text-sm text-slate-500">{{ $manga->lugar ?? 'Lugar por confirmar' }}{{ $manga->horarioCorto() ? ' · '.$manga->horarioCorto() : '' }}</div>
                        </div>
                        <div class="text-right text-base font-bold tabular-nums">
                            {{ $manga->fecha->format('d/m') }}
                            <div class="text-xs font-medium text-slate-500">{{ $manga->fecha->format('Y') }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Un resumen por sección (podio y pieza mayor); el ranking completo, a un toque. --}}
    @foreach ($ranking as $grupo)
        @php $seccion = $grupo->seccionId ? $secciones->get($grupo->seccionId) : null; @endphp
        <section class="pb-8">
            <h2 class="mb-1 text-lg font-bold text-emerald-600">🏆 Ranking {{ $grupo->nombre }} <span class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-emerald-600">por {{ mb_strtolower(\App\Models\Seccion::CRITERIOS[$grupo->criterio] ?? $grupo->criterio) }}</span></h2>
            <p class="mb-3 text-sm text-slate-500">{{ $temporada->nombre }} · {{ $grupo->numMangas === 1 ? '1 manga celebrada' : $grupo->numMangas.' mangas celebradas' }} · {{ $grupo->reglas }}</p>
            @include('public.partials.lista', ['grupo' => $grupo, 'modo' => 'temporada', 'limite' => 3])
            @if ($seccion)
                <div class="mt-3 flex justify-end">
                    <a href="{{ $seccion->urlPublica() }}" class="inline-flex min-h-10 items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:border-emerald-500 hover:text-emerald-800">Ver ranking completo y manga a manga →</a>
                </div>
            @endif
        </section>
    @endforeach

    @if ($clasifUltima->isNotEmpty())
        <section class="pb-8">
            <h2 class="mb-1 text-lg font-bold text-emerald-600">🎣 Última manga · {{ $ultimaManga->nombre }}</h2>
            <p class="mb-3 text-sm text-slate-500">{{ $ultimaManga->fecha->format('d/m/Y') }}{{ $ultimaManga->lugar ? ' · '.$ultimaManga->lugar : '' }}</p>
            @foreach ($clasifUltima as $grupo)
                @if ($clasifUltima->count() > 1)
                    <div class="mb-2 mt-4 text-sm font-bold text-slate-700">{{ $grupo->nombre }}</div>
                @endif
                @include('public.partials.lista', ['grupo' => $grupo, 'modo' => 'manga', 'limite' => 3])
            @endforeach
            <div class="mt-3 flex justify-end">
                <a href="{{ $ultimaManga->urlPublica() }}" class="inline-flex min-h-10 items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:border-emerald-500 hover:text-emerald-800">Ver la clasificación completa →</a>
            </div>
        </section>
    @endif

    <section class="pb-8">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-center">
            <p class="text-base text-slate-700">¿Eres socio de {{ $club->nombre }}?</p>
            <a href="/app" class="mt-3 inline-block rounded-xl bg-emerald-600 px-6 py-3 text-base font-semibold text-white hover:bg-emerald-500">
                Entrar a mi cuenta
            </a>
            <p class="mt-2 text-sm text-slate-500">¿Sin cuenta todavía? Pídele tu enlace de acceso al admin del club.</p>
        </div>
    </section>
@endsection
