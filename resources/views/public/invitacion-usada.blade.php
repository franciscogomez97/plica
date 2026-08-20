@extends('layouts.public')

@section('content')
    <div class="mx-auto max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 text-center">
        <h1 class="text-xl font-bold">Esta invitación ya se usó ✅</h1>
        <p class="mt-2 text-sm text-slate-400">La cuenta de {{ $socio->nombre }} ya está activa.</p>
        <a href="/app" class="mt-5 inline-block rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-500">
            Iniciar sesión
        </a>
    </div>
@endsection
