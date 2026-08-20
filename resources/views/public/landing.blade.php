@extends('layouts.public')

@section('content')
    <section class="py-10 text-center">
        <h1 class="mx-auto max-w-3xl text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
            Tu club de pesca, <span class="text-emerald-400">sin Excel y sin líos de WhatsApp</span>
        </h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-400">
            Mangas, pesajes, clasificaciones y ranking de temporada calculados solos.
            El admin mete los pesos de la plica en minutos; el club lo ve todo al momento.
        </p>
    </section>

    <section class="grid gap-4 py-8 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
            <h3 class="font-semibold text-emerald-400">📋 Mangas y secciones</h3>
            <p class="mt-2 text-sm text-slate-400">Programa la temporada, con secciones de orilla, embarcación o pato. Cada club con sus normas.</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
            <h3 class="font-semibold text-emerald-400">⚖️ Pesajes sin fórmulas</h3>
            <p class="mt-2 text-sm text-slate-400">Llega a casa, pasa los datos de las plicas y listo: clasificación de la manga y ranking de temporada, al instante.</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
            <h3 class="font-semibold text-emerald-400">🏆 El club siempre informado</h3>
            <p class="mt-2 text-sm text-slate-400">Cada socio entra con su cuenta y ve rankings, resultados y próximas mangas. Sin perseguir a nadie.</p>
        </div>
    </section>

    <section id="solicitar" class="mx-auto max-w-lg py-10">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="text-xl font-bold">Solicita acceso para tu club</h2>
            <p class="mt-1 text-sm text-slate-400">Estamos en fase piloto con clubes reales. Déjanos un contacto y hablamos.</p>

            @if (session('solicitud_ok'))
                <div class="mt-4 rounded-lg border border-emerald-700 bg-emerald-950 p-3 text-sm text-emerald-300">
                    ¡Recibido! Te escribiremos en cuanto abramos hueco para nuevos clubes.
                </div>
            @else
                <form method="POST" action="{{ route('solicitud.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm text-slate-300" for="club_nombre">Nombre del club</label>
                        <input id="club_nombre" name="club_nombre" required maxlength="120" value="{{ old('club_nombre') }}"
                               class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                        @error('club_nombre') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-300" for="email">Email de contacto</label>
                        <input id="email" name="email" type="email" required maxlength="120" value="{{ old('email') }}"
                               class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                        @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-300" for="mensaje">Cuéntanos algo del club <span class="text-slate-500">(opcional)</span></label>
                        <textarea id="mensaje" name="mensaje" rows="3" maxlength="1000"
                                  class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">{{ old('mensaje') }}</textarea>
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 font-medium text-white hover:bg-emerald-500">
                        Solicitar acceso
                    </button>
                </form>
            @endif
        </div>
    </section>
@endsection
