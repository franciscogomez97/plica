<x-filament-panels::page>
    @php
        $club = $this->getClub();
        $socio = $this->getSocio();
        $manga = $this->getManga();
        $grupos = $this->getGrupos();
    @endphp

    @include('filament.partials.estilos')

    @if ($grupos->isEmpty())
        <x-filament::section>
            <p style="opacity:.7">Todavía no hay pesajes apuntados en esta manga.</p>
        </x-filament::section>
    @endif

    @foreach ($grupos as $grupo)
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedScale" />
                    {{ $grupo->nombre }}
                    <span class="plica-etiqueta">por {{ mb_strtolower(\App\Models\Seccion::CRITERIOS[$grupo->criterio] ?? $grupo->criterio) }}</span>
                </span>
            </x-slot>
            @include('filament.partials.lista-clasificacion', ['grupo' => $grupo, 'modo' => 'manga', 'socioId' => $socio?->id])
            <div class="plica-mas">
                @if ($grupo->seccion)
                    <x-filament::button tag="a" size="sm" color="gray" href="{{ \App\Filament\App\Pages\RankingSeccion::getUrl(['seccion' => $grupo->seccion->id]) }}" wire:navigate>
                        Ranking de {{ $grupo->nombre }}
                    </x-filament::button>
                @endif
                @if ($loop->first && $club)
                    @include('partials.compartir', [
                        'titulo' => $manga->nombre.' · '.$club->nombre,
                        'texto' => \App\Services\Compartir::textoManga($manga, $grupos),
                        'url' => $manga->urlPublica(),
                    ])
                @endif
            </div>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
