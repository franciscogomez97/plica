@extends('layouts.public')

@section('title', 'Demasiadas peticiones — Plica')

@section('content')
    <div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-center">
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Error 429</p>
        <h1 class="mt-2 text-2xl font-bold">Frena un poco 🐟</h1>
        <p class="mt-3 text-base text-slate-500">Has hecho demasiadas peticiones seguidas. Espera un minuto y vuelve a intentarlo.</p>
        <a href="/" class="mt-6 inline-block w-full rounded-xl bg-emerald-700 py-3.5 text-base font-semibold text-white hover:bg-emerald-700">Ir a la portada</a>
        @auth
            <a href="/app" class="mt-3 inline-block text-sm text-emerald-700 hover:underline">Ir a mi panel</a>
        @endauth
    </div>
@endsection
