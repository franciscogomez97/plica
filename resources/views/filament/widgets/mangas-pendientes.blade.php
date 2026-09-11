<x-filament::section>
    <x-slot name="heading">
        <span style="display:inline-flex; align-items:center; gap:.5rem">
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle" style="width:1.25rem; height:1.25rem; color:rgb(245 158 11); flex:none" />
            Tienes {{ $this->getMangas()->count() === 1 ? 'una manga' : $this->getMangas()->count().' mangas' }} por gestionar
        </span>
    </x-slot>

    <div style="display:flex; flex-direction:column; gap:.75rem">
        @foreach ($this->getMangas() as $manga)
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.75rem; justify-content:space-between">
                <div>
                    <div style="font-weight:600">{{ $manga->nombre }} — {{ $manga->fecha->format('d/m/Y') }}{{ $manga->lugar ? ' · '.$manga->lugar : '' }}</div>
                    <div style="font-size:.8rem; opacity:.7">
                        {{ $manga->participacions_count > 0
                            ? $manga->participacions_count.' participaciones apuntadas'
                            : 'Sin participaciones todavía' }}
                        · Pasa lista, apunta los pesajes y márcala como celebrada.
                    </div>
                </div>
                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Resources\Mangas\MangaResource::getUrl('pesaje', ['record' => $manga]) }}"
                    size="sm"
                >
                    Pesaje
                </x-filament::button>
            </div>
        @endforeach
    </div>
</x-filament::section>
