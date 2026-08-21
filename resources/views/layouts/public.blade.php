<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Plica — gestión de clubes de pesca')</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2224%22 fill=%22%23059669%22/><ellipse cx=%2242%22 cy=%2252%22 rx=%2225%22 ry=%2215%22 fill=%22white%22/><path d=%22M63 52l21-15v30z%22 fill=%22white%22/><circle cx=%2229%22 cy=%2248%22 r=%223.5%22 fill=%22%23059669%22/></svg>">
    <meta name="theme-color" content="#059669">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <header class="border-b border-slate-800">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="{{ route('landing') }}" class="text-lg font-bold tracking-tight text-emerald-400">🎣 Plica</a>
            <nav class="flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ auth()->user()->isAdmin() ? '/admin' : '/app' }}"
                       class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-500">Mi panel</a>
                @else
                    <a href="/app" class="py-2 text-slate-300 hover:text-white">Soy pescador</a>
                    <a href="/admin" class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-500">Acceso clubes</a>
                @endauth
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-10">
        @if (session('expirado'))
            <div class="mx-auto mb-6 max-w-md rounded-xl border border-amber-700 bg-amber-950/60 p-4 text-center text-base text-amber-200">
                La página llevaba demasiado tiempo abierta y se envió sin efecto.
                Vuelve a rellenar el formulario, por favor.
            </div>
        @endif
        @yield('content')
    </main>
    <footer class="border-t border-slate-800 py-6 text-center text-xs text-slate-500">
        Plica · Gestión de clubes de pesca deportiva
    </footer>
</body>
</html>
