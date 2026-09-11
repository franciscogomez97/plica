@extends('layouts.public')

@section('title', 'Página caducada — Plica')

@section('content')
    <div class="mx-auto max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 text-center">
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Error 419</p>
        <h1 class="mt-2 text-2xl font-bold">La página se quedó vieja</h1>
        <p class="mt-3 text-base text-slate-400">Llevaba demasiado tiempo abierta y el formulario ha caducado. Vuelve atrás y repítelo; no se ha guardado nada a medias.</p>
        <a href="javascript:history.back()" class="mt-6 inline-block w-full rounded-xl bg-emerald-600 py-3.5 text-base font-semibold text-white hover:bg-emerald-500">Volver atrás</a>
        @auth
            <a href="/app" class="mt-3 inline-block text-sm text-emerald-400 hover:underline">Ir a mi panel</a>
        @endauth
    </div>
@endsection
