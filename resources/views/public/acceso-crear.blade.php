@extends('layouts.public')

@section('title', 'Únete a '.$socio->club->nombre.' — Plica')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <h1 class="text-2xl font-bold">Hola, {{ $socio->nombre }} 👋</h1>
            <p class="mt-2 text-base text-slate-400">
                Tu club <span class="font-medium text-slate-200">{{ $socio->club->nombre }}</span> te invita a crear tu
                cuenta para ver clasificaciones, rankings y próximas mangas.
            </p>

            <form method="POST" action="{{ route('acceso.claim', $token) }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="mb-1.5 block text-base font-medium text-slate-200" for="name">Tu nombre</label>
                    <input id="name" name="name" required maxlength="120" autocomplete="name"
                           value="{{ old('name', $socio->nombre) }}"
                           class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-base focus:border-emerald-500 focus:outline-none">
                    @error('name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-base font-medium text-slate-200" for="email">Tu email</label>
                    <input id="email" name="email" type="email" required maxlength="120" autocomplete="email" inputmode="email"
                           value="{{ old('email', $socio->email) }}"
                           class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-base focus:border-emerald-500 focus:outline-none">
                    @error('email') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-base font-medium text-slate-200" for="password">Elige una contraseña</label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                           class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-base focus:border-emerald-500 focus:outline-none">
                    <p class="mt-1 text-sm text-slate-500">Mínimo 8 caracteres.</p>
                    @error('password') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-base font-medium text-slate-200" for="password_confirmation">Repítela</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"
                           class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-base focus:border-emerald-500 focus:outline-none">
                </div>
                <button type="submit" class="w-full rounded-xl bg-emerald-600 py-3.5 text-base font-semibold text-white hover:bg-emerald-500">
                    Crear mi cuenta
                </button>
            </form>
        </div>
    </div>
@endsection
