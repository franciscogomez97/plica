@php $d = $this->getDatos(); @endphp

<x-filament::section>
    <x-slot name="heading">Tu club de un vistazo</x-slot>

    <style>
        .resumen-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; }
        @media (min-width: 768px) { .resumen-grid { grid-template-columns: repeat(4, 1fr); } }
        .resumen-celda { border: 1px solid rgba(128,128,128,.2); border-radius: .8rem; padding: .8rem; }
        .resumen-etiqueta { display: flex; align-items: center; gap: .35rem; font-size: .75rem; opacity: .75; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
        .resumen-etiqueta svg { width: 1rem; height: 1rem; color: rgb(16 185 129); flex: none; }
        .resumen-valor { font-size: 1.05rem; font-weight: 800; margin-top: .25rem; }
        .resumen-sub { font-size: .78rem; opacity: .6; margin-top: .1rem; }
    </style>

    <div class="resumen-grid">
        <div class="resumen-celda">
            <div class="resumen-etiqueta">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays" />
                Próxima manga
            </div>
            @if ($d->proxima)
                <div class="resumen-valor">{{ $d->proxima->fecha->format('d/m') }}</div>
                <div class="resumen-sub">{{ $d->proxima->nombre }}{{ $d->proxima->horarioCorto() ? ' · '.$d->proxima->horarioCorto() : '' }}{{ $d->proxima->lugar ? ' · '.$d->proxima->lugar : '' }}</div>
            @else
                <div class="resumen-valor">—</div>
                <div class="resumen-sub">Sin mangas programadas</div>
            @endif
        </div>
        <div class="resumen-celda">
            <div class="resumen-etiqueta">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedUsers" />
                Socios activos
            </div>
            <div class="resumen-valor">{{ $d->sociosActivos }}</div>
        </div>
        <div class="resumen-celda">
            <div class="resumen-etiqueta">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedScale" />
                Mangas celebradas
            </div>
            <div class="resumen-valor">{{ $d->celebradas }}</div>
            <div class="resumen-sub">esta temporada</div>
        </div>
        <div class="resumen-celda">
            <div class="resumen-etiqueta">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy" />
                {{ $d->lideres->count() === 1 ? 'Líder' : 'Líderes' }}
            </div>
            @forelse ($d->lideres as $l)
                <div class="resumen-sub" style="margin-top:.3rem">
                    <strong style="opacity:1">{{ $l->lider->socio->nombre }}</strong> · {{ $l->seccion }}
                </div>
            @empty
                <div class="resumen-valor">—</div>
                <div class="resumen-sub">Aún sin mangas celebradas</div>
            @endforelse
        </div>
    </div>
</x-filament::section>
