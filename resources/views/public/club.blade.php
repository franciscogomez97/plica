@extends('layouts.public')

@section('title', $club->nombre.' — Plica')

@section('content')
    <section class="pb-8">
        <h1 class="text-3xl font-extrabold tracking-tight">{{ $club->nombre }}</h1>
        <p class="mt-1 text-slate-400">
            {{ $club->localidad }}
            @if ($club->email_contacto) · <a class="text-emerald-400 hover:underline" href="mailto:{{ $club->email_contacto }}">{{ $club->email_contacto }}</a> @endif
            @if ($club->telefono_contacto) · {{ $club->telefono_contacto }} @endif
        </p>
        @if ($club->descripcion)
            <p class="mt-3 max-w-2xl text-sm text-slate-300">{{ $club->descripcion }}</p>
        @endif
    </section>

    @if ($proximas->isNotEmpty())
        <section class="pb-8">
            <h2 class="mb-3 text-lg font-bold text-emerald-400">Próximas mangas</h2>
            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900 text-left text-slate-400">
                        <tr><th class="px-4 py-2.5">Manga</th><th class="px-4 py-2.5">Fecha</th><th class="px-4 py-2.5">Lugar</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($proximas as $manga)
                            <tr class="border-t border-slate-800">
                                <td class="px-4 py-2.5">{{ $manga->nombre }}</td>
                                <td class="px-4 py-2.5">{{ $manga->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-2.5">{{ $manga->lugar ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @foreach ($ranking as $grupo)
        <section class="pb-8">
            <h2 class="mb-3 text-lg font-bold text-emerald-400">Ranking {{ $temporada->nombre }} — {{ $grupo->nombre }} ({{ \App\Models\Seccion::CRITERIOS[$grupo->criterio] ?? $grupo->criterio }})</h2>
            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900 text-left text-slate-400">
                        <tr><th class="px-4 py-2.5">Puesto</th><th class="px-4 py-2.5">Socio</th><th class="px-4 py-2.5 text-right">Mangas</th><th class="px-4 py-2.5 text-right">Peso</th><th class="px-4 py-2.5 text-right">Medida</th><th class="px-4 py-2.5 text-right">Puntos</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($grupo->filas as $fila)
                            <tr class="border-t border-slate-800 {{ $fila->puesto <= 3 ? 'font-bold text-emerald-300' : '' }}">
                                <td class="px-4 py-2.5">{{ $fila->puesto }}º</td>
                                <td class="px-4 py-2.5">{{ $fila->socio->nombre }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fila->mangas }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fila->peso > 0 ? \App\Services\Scoring::formatPeso($fila->peso) : '—' }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fila->medida > 0 ? \App\Services\Scoring::formatMedida($fila->medida) : '—' }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums font-semibold">{{ \App\Services\Scoring::formatPuntos($fila->puntos) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    @foreach ($clasifUltima as $grupo)
        <section class="pb-8">
            <h2 class="mb-3 text-lg font-bold text-emerald-400">Última manga: {{ $ultimaManga->nombre }} ({{ $ultimaManga->fecha->format('d/m/Y') }}) — {{ $grupo->nombre }}</h2>
            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900 text-left text-slate-400">
                        <tr><th class="px-4 py-2.5">Puesto</th><th class="px-4 py-2.5">Socio</th><th class="px-4 py-2.5 text-right">Piezas</th><th class="px-4 py-2.5 text-right">Peso</th><th class="px-4 py-2.5 text-right">Medida</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($grupo->filas as $fila)
                            <tr class="border-t border-slate-800 {{ $fila->puesto <= 3 ? 'font-bold text-emerald-300' : '' }}">
                                <td class="px-4 py-2.5">{{ $fila->puesto }}º</td>
                                <td class="px-4 py-2.5">{{ $fila->socio->nombre }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fila->piezas }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fila->peso > 0 ? \App\Services\Scoring::formatPeso($fila->peso) : '—' }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fila->medida > 0 ? \App\Services\Scoring::formatMedida($fila->medida) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
@endsection
