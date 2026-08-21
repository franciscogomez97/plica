<x-filament::section>
    <x-slot name="heading">¿Qué quieres hacer?</x-slot>

    <style>
        .plica-acciones { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; }
        @media (min-width: 640px) { .plica-acciones { grid-template-columns: repeat(4, 1fr); } }
        .plica-accion { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem; min-height: 5.5rem; padding: 1rem .5rem; border-radius: .9rem; border: 1px solid rgba(128,128,128,.25); text-align: center; font-weight: 600; font-size: .95rem; }
        .plica-accion:hover { border-color: rgb(16 185 129); }
        .plica-accion svg { width: 1.75rem; height: 1.75rem; color: rgb(16 185 129); }
    </style>

    <div class="plica-acciones">
        <a class="plica-accion" href="{{ \App\Filament\Resources\Mangas\MangaResource::getUrl('create') }}">
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedPlusCircle" />
            Nueva manga
        </a>
        <a class="plica-accion" href="{{ \App\Filament\Resources\Mangas\MangaResource::getUrl() }}">
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedScale" />
            Mangas y pesajes
        </a>
        <a class="plica-accion" href="{{ \App\Filament\Resources\Socios\SocioResource::getUrl() }}">
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedUsers" />
            Socios
        </a>
        <a class="plica-accion" href="{{ \App\Filament\Pages\Ranking::getUrl() }}">
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy" />
            Consultar ranking
        </a>
    </div>
</x-filament::section>
