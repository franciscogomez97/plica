<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Plica — la app de tu club de pesca')</title>
    {{-- Vista previa del enlace en WhatsApp: cada página pública pone su título y su podio. --}}
    @hasSection('meta')
        @yield('meta')
    @else
        <meta property="og:title" content="@yield('title', 'Plica — la app de tu club de pesca')">
        <meta property="og:description" content="Mangas, pesajes, clasificaciones y rankings de tu club de pesca, sin Excel y sin líos de WhatsApp.">
        <meta property="og:type" content="website">
        <meta property="og:image" content="{{ asset(\App\Support\Marca::OG) }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    {!! \App\Support\Marca::iconos() !!}
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <a href="{{ route('landing') }}" class="flex items-center gap-2 text-lg font-extrabold tracking-tight">
                <img src="{{ asset(\App\Support\Marca::LOGO) }}" alt="" class="size-9">
                Plica
            </a>
            <nav class="flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ auth()->user()->isAdmin() ? '/admin' : '/app' }}"
                       class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-500">Mi panel</a>
                @else
                    {{-- Una sola puerta: socios y admins entran por el mismo login y cada uno va a su panel. --}}
                    <a href="{{ route('filament.app.auth.login') }}" class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-500">Entrar</a>
                @endauth
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-10">
        @if (session('expirado'))
            <div class="mx-auto mb-6 max-w-md rounded-xl border border-amber-300 bg-amber-50 p-4 text-center text-base text-amber-800">
                La página llevaba demasiado tiempo abierta y se envió sin efecto.
                Vuelve a rellenar el formulario, por favor.
            </div>
        @endif
        @yield('content')
    </main>
    {{-- Cada clasificación compartida la ven socios de otros clubes: el pie es la captación. --}}
    <footer class="border-t border-slate-200 py-8 text-sm text-slate-500">
        <div class="mx-auto flex max-w-5xl flex-col gap-4 px-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <img src="{{ asset(\App\Support\Marca::LOGO) }}" alt="" class="size-6 opacity-80">
                <a href="{{ route('landing') }}" class="font-semibold text-slate-500 hover:text-emerald-700">Hecho con Plica</a>
                <span>· mangas, pesajes y rankings de tu club sin Excel</span>
            </div>
            <nav class="flex flex-wrap gap-x-4 gap-y-1">
                <a href="{{ route('legal.aviso') }}" class="hover:text-emerald-700">Aviso legal</a>
                <a href="{{ route('legal.privacidad') }}" class="hover:text-emerald-700">Privacidad</a>
                <a href="{{ route('legal.cookies') }}" class="hover:text-emerald-700">Cookies</a>
                <a href="{{ route('legal.condiciones') }}" class="hover:text-emerald-700">Condiciones</a>
            </nav>
        </div>
    </footer>
</body>
</html>
