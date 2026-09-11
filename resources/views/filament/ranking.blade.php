<x-filament-panels::page>
    @php $grupos = $this->getGrupos(); @endphp

    @include('filament.partials.estilos')

    @if ($grupos->isEmpty())
        <x-filament::section>
            <p style="opacity:.7">Aún no hay mangas celebradas esta temporada. En cuanto marques la primera como celebrada, aquí saldrá el ranking de cada sección.</p>
        </x-filament::section>
    @endif

    @foreach ($grupos as $grupo)
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy" />
                    {{ $grupo->nombre }}
                </span>
            </x-slot>
            <x-slot name="description">
                {{ $grupo->reglas }}
            </x-slot>
            @include('filament.partials.lista-clasificacion', ['grupo' => $grupo, 'modo' => 'temporada'])
            @if ($grupo->seccionId !== null)
                {{-- Al pie y no en la cabecera: en móvil la cabecera no tiene sitio para botones. --}}
                <div style="display:flex; flex-wrap:wrap; justify-content:flex-end; align-items:center; gap:.6rem; margin-top:.9rem">
                    @include('partials.compartir', [
                        'titulo' => 'Ranking '.$grupo->nombre.' · '.auth()->user()->club->nombre,
                        'texto' => \App\Services\Compartir::textoRanking(auth()->user()->club, $this->getTemporada(), $grupo),
                        'url' => route('club.seccion', ['club' => auth()->user()->club->slug, 'seccion' => $grupo->seccionSlug]),
                    ])
                    <x-filament::button
                        tag="a"
                        size="sm"
                        color="gray"
                        icon="heroicon-o-table-cells"
                        href="{{ \App\Filament\Pages\RankingSeccion::getUrl(['seccion' => $grupo->seccionId]) }}"
                        wire:navigate
                    >
                        Ver manga a manga
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
