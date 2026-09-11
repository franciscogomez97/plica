<x-filament::section>
    <x-slot name="heading">
        <span style="display:inline-flex; align-items:center; gap:.5rem">
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays" style="width:1.25rem; height:1.25rem; color:rgb(16 185 129); flex:none" />
            La {{ $this->getTemporadaActiva()->nombre }} está acabando
        </span>
    </x-slot>

    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.75rem; justify-content:space-between">
        <p style="margin:0; font-size:.92rem; opacity:.8; max-width:36rem">
            Crea la <strong>{{ $this->getNombreSiguiente() }}</strong> para que las mangas nuevas cuenten en el ranking que toca.
            El ranking de la {{ $this->getTemporadaActiva()->nombre }} se conserva tal cual y seguirá consultable.
        </p>
        <x-filament::button tag="a" href="{{ $this->getUrlCrear() }}" wire:navigate icon="heroicon-o-plus-circle">
            Crear la {{ $this->getNombreSiguiente() }}
        </x-filament::button>
    </div>
</x-filament::section>
