<x-filament::section>
    <x-slot name="heading">
        ⚠️ Tienes {{ $this->getMangas()->count() === 1 ? 'una manga' : $this->getMangas()->count().' mangas' }} por gestionar
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
                        · Marca la asistencia, mete los pesajes y márcala como celebrada.
                    </div>
                </div>
                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Resources\Mangas\MangaResource::getUrl('edit', ['record' => $manga]) }}"
                    size="sm"
                >
                    Gestionar
                </x-filament::button>
            </div>
        @endforeach
    </div>
</x-filament::section>
