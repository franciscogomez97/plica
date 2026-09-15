<x-filament-panels::page>
    @php
        $manga = $this->getRecord();
        $grupos = $this->getClasificacion();
    @endphp

    @include('filament.partials.estilos')

    @if ($grupos->isEmpty())
        <x-filament::section>
            <p style="opacity:.7">Aún no hay participaciones en esta manga. Apúntalas en el pesaje.</p>
        </x-filament::section>
    @else
        {{-- Compartir: enlace público de la manga (cualquiera con el enlace lo ve). --}}
        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.75rem">
            {{-- Un solo botón: en el móvil manda la tarjeta con el texto y el enlace; en escritorio, WhatsApp Web con el texto. --}}
            @foreach ($grupos as $g)
                @include('partials.compartir', [
                    'titulo' => $manga->nombre.($grupos->count() > 1 ? ' · '.$g->nombre : '').' · '.auth()->user()->club->nombre,
                    'texto' => \App\Services\Compartir::textoManga($manga, $grupos),
                    'url' => $manga->urlPublica(),
                    'imagen' => \App\Services\Podio::urlManga($manga, $g),
                    'etiqueta' => $grupos->count() > 1 ? 'WhatsApp · '.$g->nombre : null,
                ])
            @endforeach
            <a href="{{ $manga->urlPublica() }}" target="_blank" rel="noopener" style="font-size:.85rem; opacity:.7; text-decoration:underline">Ver la página pública</a>
        </div>
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
