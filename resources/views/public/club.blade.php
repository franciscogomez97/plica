@extends('layouts.public')

@section('title', $club->nombre.' — Plica')

@section('content')
    <section class="pb-8">
        <h1 class="text-3xl font-extrabold tracking-tight">{{ $club->nombre }}</h1>
        <p class="mt-1 text-base text-slate-400">
            {{ $club->localidad }}
            @if ($club->email_contacto) · <a class="text-emerald-400 hover:underline" href="mailto:{{ $club->email_contacto }}">{{ $club->email_contacto }}</a> @endif
            @if ($club->telefono_contacto) · {{ $club->telefono_contacto }} @endif
        </p>
        @if ($club->descripcion)
            <p class="mt-3 max-w-2xl text-base text-slate-300">{{ $club->descripcion }}</p>
        @endif
    </section>

    @if ($proximas->isNotEmpty())
        <section class="pb-8">
            <h2 class="mb-3 text-lg font-bold text-emerald-400">📅 Próximas mangas</h2>
            <div class="divide-y divide-slate-800 rounded-2xl border border-slate-800 bg-slate-900 px-4">
                @foreach ($proximas as $manga)
                    <div class="flex min-h-14 items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-base font-semibold">{{ $manga->nombre }}</div>
                            <div class="text-sm text-slate-500">{{ $manga->lugar ?? 'Lugar por confirmar' }}</div>
                        </div>
                        <div class="text-right text-base font-bold tabular-nums">
                            {{ $manga->fecha->format('d/m') }}
                            <div class="text-xs font-medium text-slate-500">{{ $manga->fecha->format('Y') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @foreach ($ranking as $grupo)
        <section class="pb-8">
            <h2 class="mb-3 text-lg font-bold text-emerald-400">🏆 Ranking {{ $temporada->nombre }} — {{ $grupo->nombre }}</h2>
            <div class="divide-y divide-slate-800 rounded-2xl border border-slate-800 bg-slate-900 px-4">
                @foreach ($grupo->filas as $fila)
                    <div class="flex min-h-14 items-center gap-3 py-3">
                        <div @class([
                            'flex size-10 flex-none items-center justify-center rounded-full text-base font-bold',
                            'bg-emerald-500/20 text-emerald-400' => $fila->puesto <= 3,
                            'bg-slate-800 text-slate-300' => $fila->puesto > 3,
                        ])>{{ $fila->puesto }}º</div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-base font-semibold">{{ $fila->socio->nombre }}</div>
                            <div class="text-sm text-slate-500">{{ $fila->mangas }} {{ $fila->mangas === 1 ? 'manga' : 'mangas' }}</div>
                        </div>
                        <div class="text-right text-base font-bold tabular-nums">
                            @if ($grupo->sistema === \App\Models\Seccion::SISTEMA_PUESTOS)
                                {{ \App\Services\Scoring::formatPuntos($fila->puntos) }} pts
                                <div class="text-xs font-medium text-slate-500">{{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}</div>
                            @else
                                {{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    @foreach ($clasifUltima as $grupo)
        <section class="pb-8">
            <h2 class="mb-3 text-lg font-bold text-emerald-400">🎣 {{ $ultimaManga->nombre }} ({{ $ultimaManga->fecha->format('d/m/Y') }}) — {{ $grupo->nombre }}</h2>
            <div class="divide-y divide-slate-800 rounded-2xl border border-slate-800 bg-slate-900 px-4">
                @foreach ($grupo->filas as $fila)
                    <div class="flex min-h-14 items-center gap-3 py-3">
                        <div @class([
                            'flex size-10 flex-none items-center justify-center rounded-full text-base font-bold',
                            'bg-emerald-500/20 text-emerald-400' => $fila->puesto <= 3,
                            'bg-slate-800 text-slate-300' => $fila->puesto > 3,
                        ])>{{ $fila->puesto }}º</div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-base font-semibold">{{ $fila->socio->nombre }}</div>
                            <div class="text-sm text-slate-500">{{ \App\Services\Scoring::valorSecundario($grupo->criterio, $fila) }}</div>
                        </div>
                        <div class="text-right text-base font-bold tabular-nums">{{ \App\Services\Scoring::valorPrincipal($grupo->criterio, $fila) }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
    <section class="pb-8">
        <div class="rounded-2xl border border-emerald-900/60 bg-emerald-950/40 p-5 text-center">
            <p class="text-base text-slate-300">¿Eres socio de {{ $club->nombre }}?</p>
            <a href="/app" class="mt-3 inline-block rounded-xl bg-emerald-600 px-6 py-3 text-base font-semibold text-white hover:bg-emerald-500">
                Entrar a mi cuenta
            </a>
            <p class="mt-2 text-sm text-slate-500">¿Sin cuenta todavía? Pídele tu enlace de acceso al admin del club.</p>
        </div>
    </section>
@endsection