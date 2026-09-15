<x-filament-panels::page>
    @include('filament.partials.estilos')

    {{-- Lo mismo que ve cualquiera en la página pública de la sección: mismo parcial, mismos datos,
         con la fila del socio resaltada («· tú»). Las mangas enlazan a su clasificación en este panel. --}}
    <div class="plica-web">
        @include('public.partials.seccion-ranking', $this->getDatos() + [
            'socioId' => $this->getSocio()?->id,
            'urlManga' => fn ($manga) => \App\Filament\App\Pages\ClasificacionManga::getUrl(['manga' => $manga->id]),
        ])
    </div>
</x-filament-panels::page>
