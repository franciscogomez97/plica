@extends('layouts.public')

@section('title', 'Nueva contraseña — Plica')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <h1 class="text-2xl font-bold">Hola de nuevo, {{ $socio->nombre }} 👋</h1>
            <p class="mt-2 text-base text-slate-500">
                Elige una contraseña nueva para tu cuenta de
                <span class="font-medium text-slate-800">{{ $socio->club->nombre }}</span> y entrarás directamente.
            </p>

            <form method="POST" action="{{ route('acceso.claim', $token) }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="mb-1.5 block text-base font-medium text-slate-800" for="password">Contraseña nueva</label>
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
                    Guardar y entrar
                </button>
            </form>
        </div>
    </div>
@endsection
