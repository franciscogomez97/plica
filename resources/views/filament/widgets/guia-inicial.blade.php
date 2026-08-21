@php
    $user = auth()->user();
    $pasos = $this->getPasos();
    $actual = $this->getPasoActual();
    $hechos = collect($pasos)->where('hecho', true)->count();
@endphp

<div>
@if ($user->guia_completada_at === null)
<x-filament::section>
    <style>
        .guia-cabecera { margin-bottom: 1rem; }
        .guia-cabecera h2 { font-size: 1.25rem; font-weight: 800; }
        .guia-cabecera p { opacity: .7; font-size: .9rem; margin-top: .2rem; }
        .guia-barra { height: .45rem; border-radius: 999px; background: rgba(128,128,128,.18); margin: .8rem 0 1.2rem; overflow: hidden; }
        .guia-barra > div { height: 100%; border-radius: 999px; background: rgb(16 185 129); transition: width .4s; }
        .guia-paso { display: flex; gap: .8rem; padding: .65rem 0; border-top: 1px solid rgba(128,128,128,.15); }
        .guia-paso:first-of-type { border-top: none; }
        .guia-num { flex: none; width: 2rem; height: 2rem; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .9rem; background: rgba(128,128,128,.15); }
        .guia-hecho .guia-num { background: rgba(16,185,129,.2); color: rgb(16 185 129); }
        .guia-hecho .guia-titulo { opacity: .55; text-decoration: line-through; }
        .guia-futuro { opacity: .45; }
        .guia-titulo { font-weight: 700; font-size: 1rem; padding-top: .3rem; }
        .guia-texto { font-size: .9rem; opacity: .75; margin-top: .35rem; line-height: 1.5; }
        .guia-boton { display: inline-block; margin-top: .7rem; padding: .65rem 1.1rem; border-radius: .7rem; background: rgb(16 185 129); color: white; font-weight: 600; font-size: .95rem; }
        .guia-omitir { display: block; margin-top: 1rem; font-size: .8rem; opacity: .5; text-align: right; cursor: pointer; background: none; border: none; }
    </style>

    @if ($actual === null)
        <div class="guia-cabecera" style="text-align:center; padding:1rem 0">
            <h2>🎉 ¡Tu club está en marcha!</h2>
            <p>Primeros pasos completados. A partir de ahora, este Inicio te mostrará los avisos y accesos de tu día a día.</p>
            <button wire:click="omitir" class="guia-boton" style="margin-top:1rem">Entendido, cerrar la guía</button>
        </div>
    @else
        <div class="guia-cabecera">
            <h2>👋 ¡Bienvenido, {{ str(auth()->user()->name)->before(' ') }}!</h2>
            <p>Te guiamos para dejar tu club listo. Ve a tu ritmo — cada paso se marca solo cuando lo completas.</p>
        </div>
        <div class="guia-barra"><div style="width: {{ round($hechos / count($pasos) * 100) }}%"></div></div>

        @foreach ($pasos as $i => $paso)
            <div @class(['guia-paso', 'guia-hecho' => $paso['hecho'] && ! ($paso['informativo'] ?? false), 'guia-futuro' => ! $paso['hecho'] && $i !== $actual])>
                <div class="guia-num" @if($paso['hecho']) style="background:rgba(16,185,129,.2); color:rgb(16 185 129)" @endif>{{ $paso['hecho'] ? '✓' : $i + 1 }}</div>
                <div style="min-width:0">
                    <div class="guia-titulo">{{ $paso['titulo'] }}</div>
                    @if ($i === $actual || ($paso['informativo'] ?? false))
                        <div class="guia-texto">{{ $paso['texto'] }}</div>
                        @if ($paso['boton'])
                            <a href="{{ $paso['url'] }}" class="guia-boton">{{ $paso['boton'] }} →</a>
                        @endif
                    @endif
                </div>
            </div>
        @endforeach

        <button wire:click="omitir" class="guia-omitir">Ya conozco el panel, omitir la guía</button>
    @endif
</x-filament::section>
@endif
</div>
