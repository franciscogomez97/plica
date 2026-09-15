<x-filament-panels::page>
    @php
        $secciones = $this->getSecciones();
        $activa = $this->getSeccionActiva();
        $datos = $this->getDatos();
    @endphp

    @include('filament.partials.estilos')

    @if ($secciones->isEmpty())
        <x-filament::section>
            <p style="opacity:.7">Todavía no hay secciones. Crea la primera en «Secciones» y, cuando se celebre una manga, aquí saldrá su ranking.</p>
        </x-filament::section>
    @else
        {{-- Una pestaña por sección, como en Mangas. Los rankings son siempre por sección: no hay «Todas». --}}
        @if ($secciones->count() > 1)
            <x-filament::tabs label="Secciones">
                @foreach ($secciones as $seccion)
                    <x-filament::tabs.item
                        tag="a"
                        :active="$activa?->id === $seccion->id"
                        :href="\App\Filament\Pages\Ranking::urlDeSeccion($seccion)"
                        wire:navigate
                    >
                        {{ $seccion->nombre }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        @endif

        {{-- Lo mismo que ve cualquiera en la página pública de la sección: mismo parcial, mismos datos. --}}
        <div class="plica-web">
            @include('public.partials.seccion-ranking', $datos + [
                'enPanel' => true,
                'urlManga' => fn ($manga) => \App\Filament\Resources\Mangas\MangaResource::getUrl('clasificacion', ['record' => $manga]),
            ])
        </div>
    @endif
</x-filament-panels::page>
