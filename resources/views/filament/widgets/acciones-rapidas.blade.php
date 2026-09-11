<x-filament::section>
    <x-slot name="heading">¿Qué quieres hacer?</x-slot>

    <style>
        .plica-acciones { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; }
        @media (min-width: 640px) { .plica-acciones { grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 1024px) { .plica-acciones { grid-template-columns: repeat(4, 1fr); } }
        .plica-accion { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem; min-height: 5.5rem; padding: 1rem .5rem; border-radius: .9rem; border: 1px solid rgba(128,128,128,.25); text-align: center; font-weight: 600; font-size: .95rem; }
        .plica-accion:hover { border-color: rgb(16 185 129); }
        .plica-accion svg { width: 1.75rem; height: 1.75rem; color: rgb(16 185 129); }
        .plica-accion.principal { background: rgb(16 185 129); color: #fff; border-color: transparent; }
        .plica-accion.principal svg { color: #fff; }
    </style>

    {{-- Un botón por cada sección del menú (el menú lateral no se ve en el móvil), y la acción más frecuente destacada. --}}
    <div class="plica-acciones">
        <a class="plica-accion principal" href="{{ \App\Filament\Resources\Mangas\MangaResource::getUrl('create') }}" wire:navigate>
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedPlusCircle" />
            Nueva manga
        </a>
        <a class="plica-accion" href="{{ \App\Filament\Resources\Mangas\MangaResource::getUrl() }}" wire:navigate>
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedScale" />
            Mangas y pesajes
        </a>
        <a class="plica-accion" href="{{ \App\Filament\Resources\Socios\SocioResource::getUrl() }}" wire:navigate>
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedUsers" />
            Socios
        </a>
        <a class="plica-accion" href="{{ \App\Filament\Pages\Ranking::getUrl() }}" wire:navigate>
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy" />
            Ranking
        </a>
        <a class="plica-accion" href="{{ \App\Filament\Resources\Seccions\SeccionResource::getUrl() }}" wire:navigate>
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTag" />
            Secciones
        </a>
        <a class="plica-accion" href="{{ \App\Filament\Resources\Temporadas\TemporadaResource::getUrl() }}" wire:navigate>
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays" />
            Temporadas
        </a>
        <a class="plica-accion" href="{{ \App\Filament\Pages\MiClub::getUrl() }}" wire:navigate>
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedIdentification" />
            Mi club
        </a>
        @if (\App\Filament\Resources\Solicituds\SolicitudResource::canViewAny())
            <a class="plica-accion" href="{{ \App\Filament\Resources\Solicituds\SolicitudResource::getUrl() }}" wire:navigate>
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedInbox" />
                Solicitudes de acceso
            </a>
        @endif
    </div>
</x-filament::section>
