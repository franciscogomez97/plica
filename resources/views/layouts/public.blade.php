<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Plica — gestión de clubes de pesca')</title>
    <script src="https://cdn.tailwindcss.com"></script>{{-- Piloto: sustituir por build propio antes de producción --}}
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <header class="border-b border-slate-800">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="{{ route('landing') }}" class="text-lg font-bold tracking-tight text-emerald-400">🎣 Plica</a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="/app" class="text-slate-300 hover:text-white">Soy pescador</a>
                <a href="/admin" class="rounded-lg bg-emerald-600 px-3 py-1.5 font-medium text-white hover:bg-emerald-500">Acceso clubes</a>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-10">
        @yield('content')
    </main>
    <footer class="border-t border-slate-800 py-6 text-center text-xs text-slate-500">
        Plica · Gestión de clubes de pesca deportiva
    </footer>
</body>
</html>
