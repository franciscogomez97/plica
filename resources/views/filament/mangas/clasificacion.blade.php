<x-filament-panels::page>
    @php
        $manga = $this->getRecord();
        $grupos = $this->getClasificacion();
    @endphp

    @include('filament.partials.estilos')

    @if ($grupos->isEmpty())
        <x-filament::section>
            <p style="opacity:.7">Aún no hay participaciones en esta manga. Añádelas en la pestaña «Participaciones y pesajes».</p>
        </x-filament::section>
    @endif

    @foreach ($grupos as $grupo)
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy" />
                    {{ $grupo->nombre }} · por {{ mb_strtolower(\App\Models\Seccion::CRITERIOS[$grupo->criterio] ?? $grupo->criterio) }}
                </span>
            </x-slot>
            <x-slot name="description">
                {{ $manga->fecha->format('d/m/Y') }}{{ $manga->lugar ? ' · '.$manga->lugar : '' }}
            </x-slot>
            @include('filament.partials.lista-clasificacion', ['grupo' => $grupo, 'modo' => 'manga'])
        </x-filament::section>
    @endforeach

    @if ($manga->estado === \App\Models\Manga::ESTADO_PROGRAMADA)
        <p style="opacity:.6; font-size:.8rem">Clasificación provisional: la manga aún no está marcada como celebrada, así que no puntúa para el ranking de temporada.</p>
    @endif
</x-filament-panels::page>
