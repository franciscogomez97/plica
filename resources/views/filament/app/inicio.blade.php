<x-filament-panels::page>
    @php
        $club = $this->getClub();
        $socio = $this->getSocio();
        $temporada = $this->getTemporada();
        $proximas = $this->getProximasMangas();
        $rankingGrupos = $this->getRanking();
        $ultimaManga = $this->getUltimaManga();
        $clasifGrupos = $this->getClasificacionUltimaManga();
    @endphp

    @include('filament.partials.estilos')

    @if (auth()->user()->guia_completada_at === null)
        <x-filament::section>
            <div style="line-height:1.6">
                <div style="font-size:1.15rem; font-weight:800">👋 Hola, {{ str(auth()->user()->name)->before(' ') }} — bienvenido a {{ $club?->nombre }}</div>
                <p style="opacity:.75; font-size:.92rem; margin-top:.5rem">
                    Aquí verás las <strong>próximas mangas</strong>, las <strong>clasificaciones</strong> y el
                    <strong>ranking</strong> — tu posición, resaltada en verde. Para cambiar tu contraseña:
                    menú de arriba → «Perfil».
                </p>
                <button wire:click="ocultarGuia"
                        style="margin-top:.8rem; padding:.6rem 1.1rem; border-radius:.7rem; background:rgb(16 185 129); color:white; font-weight:600; font-size:.95rem">
                    ¡Entendido!
                </button>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">
            <span class="plica-h">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays" />
                Próximas mangas
            </span>
        </x-slot>
        @if ($proximas->isEmpty())
            <p style="opacity:.7">No hay mangas programadas ahora mismo.</p>
        @else
            <div class="plica-lista">
                @foreach ($proximas as $manga)
                    <div class="plica-fila">
                        <div class="plica-quien">
                            <div class="plica-nombre">{{ $manga->nombre }}{{ $manga->seccion ? ' · '.$manga->seccion->nombre : '' }}</div>
                            <div class="plica-detalle">{{ $manga->lugar ?? 'Lugar por confirmar' }}</div>
                        </div>
                        <div class="plica-valor">
                            {{ $manga->fecha->format('d/m') }}
                            <small>{{ $manga->fecha->format('Y') }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    @foreach ($clasifGrupos as $grupo)
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedScale" />
                    {{ $ultimaManga->nombre }} · {{ $grupo->nombre }}
                </span>
            </x-slot>
            @include('filament.partials.lista-clasificacion', ['grupo' => $grupo, 'modo' => 'manga', 'socioId' => $socio?->id])
        </x-filament::section>
    @endforeach

    @foreach ($rankingGrupos as $grupo)
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy" />
                    Ranking {{ $temporada?->nombre }} · {{ $grupo->nombre }}
                </span>
            </x-slot>
            @include('filament.partials.lista-clasificacion', ['grupo' => $grupo, 'modo' => 'temporada', 'socioId' => $socio?->id])
        </x-filament::section>
    @endforeach

    @if ($rankingGrupos->isEmpty())
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy" />
                    Ranking
                </span>
            </x-slot>
            <p style="opacity:.7">Aún no hay mangas celebradas esta temporada.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
