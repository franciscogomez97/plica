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
                {{ $grupo->sistema === \App\Models\Seccion::SISTEMA_PUESTOS ? 'Por puestos: gana quien menos suma.' : 'Suma total: gana quien más acumula.' }}
            </x-slot>
            @include('filament.partials.lista-clasificacion', ['grupo' => $grupo, 'modo' => 'temporada'])
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
