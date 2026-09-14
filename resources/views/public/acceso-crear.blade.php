@extends('layouts.public')

@section('title', 'Únete a '.$socio->club->nombre.' — Plica')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <h1 class="text-2xl font-bold">Hola, {{ $socio->nombre }} 👋</h1>
            <p class="mt-2 text-base text-slate-500">
                Tu club <span class="font-medium text-slate-800">{{ $socio->club->nombre }}</span> te invita a crear tu
                cuenta para ver clasificaciones, rankings y próximas mangas.
            </p>

            <form method="POST" action="{{ route('acceso.claim', $token) }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="mb-1.5 block text-base font-medium text-slate-800" for="name">Tu nombre</label>
                    <input id="name" name="name" required maxlength="120" autocomplete="name"
                           value="{{ old('name', $socio->nombre) }}"
                           class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-base focus:border-emerald-500 focus:outline-none">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-base font-medium text-slate-800" for="email">Tu email</label>
                    <input id="email" name="email" type="email" required maxlength="120" autocomplete="email" inputmode="email"
                           value="{{ old('email', $socio->email) }}"
                           class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-base focus:border-emerald-500 focus:outline-none">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-base font-medium text-slate-800" for="password">Elige una contraseña</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 pr-16 text-base focus:border-emerald-500 focus:outline-none">
                        <button type="button" aria-label="Mostrar u ocultar la contraseña"
                                onclick="const c=document.getElementById('password'),o=document.getElementById('password_confirmation'),v=c.type==='password'?'text':'password';c.type=v;if(o)o.type=v;this.innerText=v==='text'?'Ocultar':'Ver';"
                                class="absolute inset-y-0 right-0 px-4 text-sm font-semibold text-emerald-600">Ver</button>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">Mínimo 8 caracteres.</p>
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-base font-medium text-slate-800" for="password_confirmation">Repítela</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"
                           class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-base focus:border-emerald-500 focus:outline-none">
                </div>
                <button type="submit" class="w-full rounded-xl bg-emerald-600 py-3.5 text-base font-semibold text-white hover:bg-emerald-500">
                    Crear mi cuenta
                </button>
            </form>
        </div>
    </div>
@endsection
