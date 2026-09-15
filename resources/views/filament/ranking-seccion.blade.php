<x-filament-panels::page>
    @include('filament.partials.estilos')

    {{-- Lo mismo que ve cualquiera en la página pública de la sección: mismo parcial, mismos datos. --}}
    <div class="plica-web">
        @include('public.partials.seccion-ranking', $this->getDatos() + [
            'enPanel' => true,
            'urlManga' => fn ($manga) => \App\Filament\Resources\Mangas\MangaResource::getUrl('clasificacion', ['record' => $manga]),
        ])
    </div>
</x-filament-panels::page>
