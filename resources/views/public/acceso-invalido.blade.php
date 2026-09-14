@extends('layouts.public')

@section('title', 'Enlace no válido — Plica')

@section('content')
    <div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-center">
        <h1 class="text-2xl font-bold">Este enlace ya no vale 🎣</h1>
        <p class="mt-3 text-base text-slate-500">
            Los enlaces de acceso son de un solo uso. Si ya creaste tu cuenta, entra con tu email y contraseña.
            Si no, pídele otro enlace al admin de tu club.
        </p>
        <a href="/app" class="mt-6 inline-block w-full rounded-xl bg-emerald-600 py-3.5 text-base font-semibold text-white hover:bg-emerald-500">
            Iniciar sesión
        </a>
    </div>
@endsection
