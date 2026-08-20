@extends('layouts.public')

@section('title', 'Únete a '.$socio->club->nombre.' — Plica')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <h1 class="text-xl font-bold">Hola, {{ $socio->nombre }} 👋</h1>
            <p class="mt-1 text-sm text-slate-400">
                Tu club <span class="font-medium text-slate-200">{{ $socio->club->nombre }}</span> te invita a crear tu cuenta
                para ver clasificaciones, rankings y próximas mangas.
            </p>

            <form method="POST" action="{{ route('invitacion.claim', $token) }}" class="mt-5 space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-sm text-slate-300" for="name">Tu nombre</label>
                    <input id="name" name="name" required maxlength="120" value="{{ old('name', $socio->nombre) }}"
                           class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-300" for="email">Email</label>
                    <input id="email" name="email" type="email" required maxlength="120" value="{{ old('email', $socio->email) }}"
                           class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-300" for="password">Contraseña</label>
                    <input id="password" name="password" type="password" required minlength="8"
                           class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    @error('password') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-300" for="password_confirmation">Repite la contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8"
                           class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                </div>
                <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 font-medium text-white hover:bg-emerald-500">
                    Crear mi cuenta
                </button>
            </form>
        </div>
    </div>
@endsection
