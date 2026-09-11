{{-- Marco común de las páginas legales. Espera: $titulo, $intro (opcional) y el slot. --}}
@extends('layouts.public')

@section('title', $titulo.' — Plica')

@section('content')
    @php $legal = config('plica.legal'); @endphp
    <article class="mx-auto max-w-3xl">
        <p class="text-sm font-bold uppercase tracking-wide text-emerald-400">Legal</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $titulo }}</h1>
        <p class="mt-2 text-sm text-slate-500">Última actualización: {{ $legal['actualizado'] }}</p>

        <div class="legal mt-8 space-y-4 text-base leading-relaxed text-slate-300">
            @yield('legal')
        </div>

        <nav class="mt-10 flex flex-wrap gap-x-4 gap-y-1 border-t border-slate-800 pt-6 text-sm text-slate-500">
            <a href="{{ route('legal.aviso') }}" class="hover:text-emerald-400">Aviso legal</a>
            <a href="{{ route('legal.privacidad') }}" class="hover:text-emerald-400">Privacidad</a>
            <a href="{{ route('legal.cookies') }}" class="hover:text-emerald-400">Cookies</a>
            <a href="{{ route('legal.condiciones') }}" class="hover:text-emerald-400">Condiciones del servicio</a>
        </nav>
    </article>

    <style>
        .legal h2 { margin-top: 2rem; font-size: 1.25rem; font-weight: 800; color: #f1f5f9; letter-spacing: -.01em; }
        .legal h3 { margin-top: 1.25rem; font-size: 1.05rem; font-weight: 700; color: #e2e8f0; }
        .legal ul { list-style: disc; padding-left: 1.4rem; }
        .legal li { margin-top: .35rem; }
        .legal a { color: #34d399; text-decoration: underline; }
        .legal dl { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1rem; }
        .legal dt { color: #94a3b8; }
        .legal .titular { border-radius: 1rem; border: 1px solid #1e293b; background: #0f172a; padding: 1rem 1.25rem; }
        @media (max-width: 480px) { .legal dl { grid-template-columns: 1fr; gap: 0; } .legal dd { margin-bottom: .5rem; } }
    </style>
@endsection
