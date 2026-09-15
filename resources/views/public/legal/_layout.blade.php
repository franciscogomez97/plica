{{-- Marco común de las páginas legales. Espera: $titulo, $intro (opcional) y el slot. --}}
@extends('layouts.public')

@section('title', $titulo.' — Plica')

@section('content')
    @php $legal = config('plica.legal'); @endphp
    <article class="mx-auto max-w-3xl">
        <p class="text-sm font-bold uppercase tracking-wide text-emerald-700">Legal</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $titulo }}</h1>
        <p class="mt-2 text-sm text-slate-500">Última actualización: {{ $legal['actualizado'] }}</p>

        <div class="legal mt-8 space-y-4 text-base leading-relaxed text-slate-700">
            @yield('legal')
        </div>

        <nav class="mt-10 flex flex-wrap gap-x-4 gap-y-1 border-t border-slate-200 pt-6 text-sm text-slate-500">
            <a href="{{ route('legal.aviso') }}" class="hover:text-emerald-800">Aviso legal</a>
            <a href="{{ route('legal.privacidad') }}" class="hover:text-emerald-800">Privacidad</a>
            <a href="{{ route('legal.cookies') }}" class="hover:text-emerald-800">Cookies</a>
            <a href="{{ route('legal.condiciones') }}" class="hover:text-emerald-800">Condiciones del servicio</a>
        </nav>
    </article>

    <style>
        .legal h2 { margin-top: 2rem; font-size: 1.25rem; font-weight: 800; color: #0f172a; letter-spacing: -.01em; }
        .legal h3 { margin-top: 1.25rem; font-size: 1.05rem; font-weight: 700; color: #1e293b; }
        .legal ul { list-style: disc; padding-left: 1.4rem; }
        .legal li { margin-top: .35rem; }
        .legal a { color: #059669; text-decoration: underline; }
        .legal dl { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1rem; }
        .legal dt { color: #64748b; }
        .legal .titular { border-radius: 1rem; border: 1px solid #e2e8f0; background: #f8fafc; padding: 1rem 1.25rem; }
        @media (max-width: 480px) { .legal dl { grid-template-columns: 1fr; gap: 0; } .legal dd { margin-bottom: .5rem; } }
    </style>
@endsection
