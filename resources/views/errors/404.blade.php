@extends('layouts.public')

@section('title', 'No encontrado — Plica')

@section('content')
    <div class="mx-auto max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 text-center">
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Error 404</p>
        <h1 class="mt-2 text-2xl font-bold">Esta página no existe 🎣</h1>
        <p class="mt-3 text-base text-slate-400">Puede que el enlace esté mal copiado o que lo que buscabas ya no esté. Los enlaces de acceso, por ejemplo, son de un solo uso.</p>
        <a href="/" class="mt-6 inline-block w-full rounded-xl bg-emerald-600 py-3.5 text-base font-semibold text-white hover:bg-emerald-500">Ir a la portada</a>
        @auth
            <a href="/app" class="mt-3 inline-block text-sm text-emerald-400 hover:underline">Ir a mi panel</a>
        @endauth
    </div>
@endsection
