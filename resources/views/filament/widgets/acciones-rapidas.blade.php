@php
    $club = auth()->user()->club;
@endphp

<x-filament::section>
    <x-slot name="heading">¿Qué quieres hacer?</x-slot>

    <style>
        .plica-acciones { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; }
        @media (min-width: 640px) { .plica-acciones { grid-template-columns: repeat(4, 1fr); } }
        .plica-accion { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .45rem; min-height: 5.5rem; padding: 1rem .5rem; border-radius: .9rem; border: 1px solid rgba(128,128,128,.25); text-align: center; font-weight: 600; font-size: .95rem; }
        .plica-accion:hover { border-color: rgb(16 185 129); }
        .plica-accion span { font-size: 1.6rem; line-height: 1; }
    </style>

    <div class="plica-acciones">
        <a class="plica-accion" href="{{ \App\Filament\Resources\Mangas\MangaResource::getUrl('create') }}">
            <span>➕</span> Nueva manga
        </a>
        <a class="plica-accion" href="{{ \App\Filament\Resources\Mangas\MangaResource::getUrl() }}">
            <span>⚖️</span> Mangas y pesajes
        </a>
        <a class="plica-accion" href="{{ \App\Filament\Resources\Socios\SocioResource::getUrl() }}">
            <span>👥</span> Socios
        </a>
        @if ($club?->perfil_publico)
            <a class="plica-accion" href="{{ route('club.publico', $club) }}" target="_blank" rel="noopener">
                <span>🌐</span> Web del club
            </a>
        @endif
    </div>
</x-filament::section>
