<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top:1.25rem; display:flex; gap:.75rem; align-items:center; flex-wrap:wrap">
            <x-filament::button type="submit" size="lg">Guardar</x-filament::button>
            @if ($this->getClub()->perfil_publico)
                <a href="{{ route('club.publico', $this->getClub()) }}" target="_blank" rel="noopener" style="font-size:.9rem; opacity:.75; text-decoration:underline">Ver la portada pública</a>
            @endif
        </div>
    </form>
</x-filament-panels::page>
