<x-filament-panels::page>
    @php
        $manga = $this->getRecord();
        $grupos = $this->getGrupos();
        $disponibles = $this->getSociosDisponibles();
    @endphp

    @include('filament.partials.estilos')

    <style>
        .pesaje-add { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; }
        .pesaje-select { flex: 1 1 14rem; min-height: 2.9rem; padding: .5rem 2.2rem .5rem .8rem; border-radius: .7rem; border: 1px solid rgba(128,128,128,.4); background: transparent; font-size: 1rem; font-weight: 500; }
        .pesaje-lista { display: flex; flex-direction: column; }
        .pesaje-fila { display: flex; flex-wrap: wrap; align-items: center; gap: .45rem .8rem; padding: .7rem .35rem; border-top: 1px solid rgba(128,128,128,.18); }
        .pesaje-fila:first-child { border-top: none; }
        .pesaje-quien { flex: 1 1 11rem; min-width: 0; display: flex; align-items: center; gap: .5rem; }
        .pesaje-texto { min-width: 0; flex: 1; }
        .pesaje-nombre { font-size: 1rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pesaje-estado { font-size: .8rem; opacity: .6; }
        .pesaje-estado.guardado { color: rgb(16 185 129); opacity: 1; font-weight: 600; }
        .pesaje-estado.error { color: rgb(239 68 68); opacity: 1; font-weight: 600; }
        .pesaje-inputs { display: flex; gap: .5rem; flex: 1 1 17rem; }
        .pesaje-campo { position: relative; flex: 1 1 0; min-width: 0; }
        .pesaje-campo.corta { flex: 0 0 5.5rem; }
        .pesaje-campo input { width: 100%; min-height: 2.9rem; padding: .5rem 2.6rem .5rem .8rem; border-radius: .7rem; border: 1px solid rgba(128,128,128,.4); background: transparent; font-size: 1.05rem; font-weight: 600; font-variant-numeric: tabular-nums; }
        .pesaje-campo input:focus { outline: none; border-color: rgb(16 185 129); box-shadow: 0 0 0 3px rgba(16,185,129,.25); }
        .pesaje-campo.error input { border-color: rgb(239 68 68); }
        .pesaje-unidad { position: absolute; right: .8rem; top: 50%; transform: translateY(-50%); font-size: .78rem; opacity: .55; pointer-events: none; }
        .pesaje-quitar { flex: none; width: 2rem; height: 2rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; opacity: .45; }
        .pesaje-quitar:hover { opacity: 1; background: rgba(239,68,68,.12); color: rgb(239 68 68); }
        .pesaje-quitar svg { width: 1.1rem; height: 1.1rem; }
        .pesaje-guardando { position: fixed; right: 1rem; bottom: 1rem; z-index: 40; padding: .5rem .9rem; border-radius: 999px; background: rgb(16 185 129); color: white; font-size: .85rem; font-weight: 600; box-shadow: 0 6px 20px rgba(0,0,0,.2); }
        .pesaje-cierre { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
        /* Móvil: tres casillas por fila (piezas, total, mayor) sin que el número roce la unidad. */
        @media (max-width: 480px) {
            .pesaje-campo input { padding: .5rem 1.8rem .5rem .6rem; font-size: 1rem; }
            .pesaje-unidad { right: .45rem; font-size: .7rem; }
            .pesaje-campo.corta { flex-basis: 4.6rem; }
        }
    </style>

    {{-- Enter salta a la siguiente casilla; al entrar en una, se selecciona lo que hay para sobrescribir.
         Las casillas llevan wire:model.live.blur: en Livewire 4, «.blur» a secas solo actualiza el estado
         del navegador; la petición al servidor al perder el foco necesita «.live.blur». --}}
    <div
        x-data="{
            siguiente(e) {
                const inputs = [...$el.querySelectorAll('.pesaje-campo input')];
                const i = inputs.indexOf(e.target);
                if (i >= 0 && inputs[i + 1]) inputs[i + 1].focus(); else e.target.blur();
            },
        }"
        x-on:keydown.enter="if ($event.target.matches('.pesaje-campo input')) { $event.preventDefault(); siguiente($event) }"
        x-on:focusin="if ($event.target.matches('.pesaje-campo input')) $event.target.select()"
        x-on:pesaje-enfocar.window="requestAnimationFrame(() => $el.querySelector(`[data-fila='${$event.detail.id}'] input`)?.focus())"
        style="display:flex; flex-direction:column; gap:1.5rem"
    >
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedUserPlus" />
                    ¿Ha venido alguien más?
                </span>
            </x-slot>
            <div class="pesaje-add">
                <select id="pesaje-nuevo-socio" class="pesaje-select" wire:model.live="nuevoSocioId" aria-label="{{ $manga->porEquipos() ? 'Añadir un equipo a la manga' : 'Añadir a un socio a la manga' }}">
                    <option value="">{{ $manga->porEquipos() ? 'Elegir equipo…' : 'Elegir socio…' }}</option>
                    @foreach ($disponibles as $grupo => $socios)
                        <optgroup label="{{ $grupo }}">
                            @foreach ($socios as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            @if ($grupos->isEmpty())
                @php $confirmados = $this->getConfirmados(); @endphp
                <p style="opacity:.7; margin-top:.8rem">
                    Todavía no hay nadie apuntado. «Marcar asistencia» pasa lista de golpe; aquí se añade uno a uno.
                    @if ($confirmados->isNotEmpty())
                        <br><strong>{{ $confirmados->count() === 1 ? '1 socio confirmó que vendría' : $confirmados->count().' socios confirmaron que vendrían' }}:</strong>
                        {{ $confirmados->implode(', ') }}. En «Marcar asistencia» ya vienen marcados.
                    @endif
                </p>
            @endif
        </x-filament::section>

        @foreach ($grupos as $grupo)
            <x-filament::section>
                <x-slot name="heading">
                    <span class="plica-h">
                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedScale" />
                        {{ $grupo->nombre }} · por {{ mb_strtolower(\App\Models\Seccion::CRITERIOS[$grupo->criterio] ?? $grupo->criterio) }}
                    </span>
                </x-slot>
                <x-slot name="description">
                    @if ($grupo->criterio === \App\Models\Seccion::CRITERIO_MEDIDA)
                        Centímetros de cada pez separados por espacios («58,5 62 45»). Se guarda solo al salir de la casilla.
                    @elseif ($grupo->criterio === \App\Models\Seccion::CRITERIO_PIEZAS)
                        Piezas, peso total en gramos y pieza mayor. Se guarda solo al salir de la casilla.
                    @else
                        Piezas, peso total en gramos («3450» o «3,450») y pieza mayor. Se guarda solo al salir de la casilla.
                    @endif
                </x-slot>

                <div class="pesaje-lista">
                    @foreach ($grupo->participaciones as $p)
                        @php $estado = $estados[$p->id] ?? ['tipo' => 'vacio', 'texto' => 'Sin capturas']; @endphp
                        <div class="pesaje-fila" wire:key="fila-{{ $p->id }}" data-fila="{{ $p->id }}">
                            <div class="pesaje-quien">
                                @if ($estado['tipo'] === 'vacio')
                                    <button type="button" class="pesaje-quitar" tabindex="-1" wire:click="quitar({{ $p->id }})" title="Quitar de la manga" aria-label="Quitar a {{ $p->participante()->nombre }} de la manga">
                                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedXMark" />
                                    </button>
                                @endif
                                <div class="pesaje-texto">
                                    <div class="pesaje-nombre">{{ $p->participante()->nombre }}</div>
                                    {{-- Un equipo con nombre propio: debajo, quiénes son. --}}
                                    @if ($p->participante()->esEquipo && filled($p->equipo->nombre))
                                        <div class="pesaje-estado" style="opacity:.7">{{ $p->equipo->miembrosTexto() }}</div>
                                    @endif
                                    <div class="pesaje-estado {{ $estado['tipo'] }}">{{ $estado['tipo'] === 'guardado' ? '✓ ' : '' }}{{ $estado['texto'] }}</div>
                                </div>
                            </div>
                            <div class="pesaje-inputs">
                                @if ($grupo->criterio === \App\Models\Seccion::CRITERIO_MEDIDA)
                                    <label class="pesaje-campo {{ $estado['tipo'] === 'error' ? 'error' : '' }}">
                                        <input id="medidas-{{ $p->id }}" type="text" inputmode="decimal" enterkeyhint="next" autocomplete="off" placeholder="58,5 62 45" wire:model.live.blur="filas.{{ $p->id }}.medidas">
                                        <span class="pesaje-unidad">cm</span>
                                    </label>
                                @else
                                    <label class="pesaje-campo corta {{ $estado['tipo'] === 'error' ? 'error' : '' }}">
                                        <input id="piezas-{{ $p->id }}" type="text" inputmode="numeric" enterkeyhint="next" autocomplete="off" placeholder="0" wire:model.live.blur="filas.{{ $p->id }}.piezas">
                                        <span class="pesaje-unidad">pzs</span>
                                    </label>
                                    <label class="pesaje-campo {{ $estado['tipo'] === 'error' ? 'error' : '' }}" title="Peso total">
                                        <input id="peso-{{ $p->id }}" type="text" inputmode="numeric" enterkeyhint="next" autocomplete="off" placeholder="total" wire:model.live.blur="filas.{{ $p->id }}.peso">
                                        <span class="pesaje-unidad">g</span>
                                    </label>
                                    {{-- Pieza mayor: siempre hay premio. Con un solo pez se rellena sola. --}}
                                    <label class="pesaje-campo {{ $estado['tipo'] === 'error' ? 'error' : '' }}" title="Pieza mayor: el pez más grande">
                                        <input id="mayor-{{ $p->id }}" type="text" inputmode="numeric" enterkeyhint="next" autocomplete="off" placeholder="mayor" wire:model.live.blur="filas.{{ $p->id }}.mayor">
                                        <span class="pesaje-unidad">g</span>
                                    </label>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach

        @if ($grupos->isNotEmpty())
            <div class="pesaje-cierre">
                @if ($manga->estado === \App\Models\Manga::ESTADO_CELEBRADA)
                    <span style="font-size:.9rem; opacity:.7">Manga celebrada: cada cambio de aquí se refleja al momento en el ranking.</span>
                @else
                    {{ $this->celebrarAction }}
                @endif
            </div>
        @endif
    </div>

    <div wire:loading class="pesaje-guardando">Guardando…</div>
</x-filament-panels::page>
