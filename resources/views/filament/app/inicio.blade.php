<x-filament-panels::page>
    @php
        $club = $this->getClub();
        $socio = $this->getSocio();
        $temporada = $this->getTemporada();
        $proximas = $this->getProximasMangas();
        $rankingGrupos = $this->getRanking();
        $ultimaManga = $this->getUltimaManga();
        $clasifGrupos = $this->getClasificacionUltimaManga();
    @endphp

    @include('filament.partials.estilos')

    <style>
        .plica-rsvp { display: inline-flex; align-items: center; gap: .4rem; min-height: 2.5rem; padding: .45rem 1rem; border-radius: .7rem; border: 1px solid rgb(16 185 129); color: rgb(16 185 129); font-weight: 700; font-size: .9rem; }
        .plica-rsvp.on { background: rgb(16 185 129); color: #fff; }
        .plica-mapa { display: inline-flex; align-items: center; min-height: 2.5rem; padding: .45rem .8rem; border-radius: .7rem; border: 1px solid rgba(128,128,128,.35); font-size: .9rem; font-weight: 600; opacity: .85; }
        /* Tu puesto, de un vistazo, arriba de cada sección. */
        .plica-yo-resumen { display: flex; align-items: center; gap: .6rem; margin-bottom: .6rem; padding: .55rem .8rem; border-radius: .7rem; background: rgba(16, 185, 129, .1); font-size: .9rem; }
        .plica-yo-resumen strong { font-size: 1.05rem; }
    </style>

    @if (auth()->user()->guia_completada_at === null)
        <x-filament::section>
            <div style="line-height:1.6">
                <div style="font-size:1.15rem; font-weight:800">👋 Hola, {{ str(auth()->user()->name)->before(' ') }} — bienvenido a {{ $club?->nombre }}</div>
                <p style="opacity:.75; font-size:.92rem; margin-top:.5rem">
                    Aquí verás las <strong>próximas mangas</strong> (y podrás decir si vas), el <strong>ranking</strong>
                    de cada sección con tu puesto resaltado, y la <strong>última manga</strong>. Para cambiar tu contraseña:
                    menú de arriba → «Perfil».
                </p>
                <button wire:click="ocultarGuia"
                        style="margin-top:.8rem; padding:.6rem 1.1rem; border-radius:.7rem; background:rgb(16 185 129); color:white; font-weight:600; font-size:.95rem">
                    ¡Entendido!
                </button>
            </div>
        </x-filament::section>
    @endif

    @include('filament.partials.instalar-app')

    <x-filament::section>
        <x-slot name="heading">
            <span class="plica-h">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays" />
                Próximas mangas
            </span>
        </x-slot>
        @if ($proximas->isEmpty())
            <p style="opacity:.7">No hay mangas programadas ahora mismo.</p>
        @else
            <div class="plica-lista">
                @foreach ($proximas as $manga)
                    @php $voy = $socio ? $manga->confirmadoPor($socio) : false; @endphp
                    <div class="plica-fila" style="flex-wrap:wrap" wire:key="proxima-{{ $manga->id }}">
                        <div class="plica-quien">
                            <div class="plica-nombre">{{ $manga->nombre }}{{ $manga->seccion ? ' · '.$manga->seccion->nombre : '' }}</div>
                            <div class="plica-detalle">
                                {{ $manga->lugar ?? 'Lugar por confirmar' }}{{ $manga->horarioCorto() ? ' · '.$manga->horarioCorto() : '' }}
                                · {{ $manga->confirmacions_count === 1 ? '1 confirmado' : $manga->confirmacions_count.' confirmados' }}
                            </div>
                            @if ($manga->quedada())
                                <div class="plica-detalle">🤝 Quedada previa {{ $manga->quedada() }}</div>
                            @endif
                        </div>
                        <div class="plica-valor">
                            {{ $manga->fecha->format('d/m') }}
                            <small>{{ $manga->fecha->format('Y') }}</small>
                        </div>
                        @if ($socio || $manga->ubicacion_url || $manga->quedada_url)
                            <div style="flex-basis:100%; display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.5rem">
                                @if ($manga->quedada_url)
                                    <a class="plica-mapa" href="{{ $manga->quedada_url }}" target="_blank" rel="noopener">🤝 Quedada</a>
                                @endif
                                @if ($manga->ubicacion_url)
                                    <a class="plica-mapa" href="{{ $manga->ubicacion_url }}" target="_blank" rel="noopener">📍 Cómo llegar</a>
                                @endif
                                @if ($socio && $socio->activo)
                                    {{-- Intención, no asistencia: la asistencia real la pasa el admin el día de la manga. --}}
                                    <button type="button" class="plica-rsvp {{ $voy ? 'on' : '' }}" wire:click="confirmar({{ $manga->id }})"
                                            title="{{ $voy ? 'Has dicho que irás. Toca para cancelar.' : 'Avisa al club de que irás' }}">
                                        {{ $voy ? '✓ Asistiré' : 'Asistiré' }}
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{-- Ranking: un resumen por sección (podio y tu puesto); el completo, a un toque. --}}
    @foreach ($rankingGrupos as $grupo)
        @php $miFila = $socio ? $grupo->filas->first(fn ($f) => $f->participante->incluye($socio->id)) : null; @endphp
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy" />
                    Ranking {{ $grupo->nombre }}
                    <span class="plica-etiqueta">por {{ mb_strtolower(\App\Models\Seccion::CRITERIOS[$grupo->criterio] ?? $grupo->criterio) }}</span>
                </span>
            </x-slot>
            {{-- Resumen: las reglas completas están en «Ver ranking completo». --}}
            <x-slot name="description">{{ $temporada?->nombre }} · {{ $grupo->numMangas === 1 ? '1 manga celebrada' : $grupo->numMangas.' mangas celebradas' }}</x-slot>
            @if ($miFila)
                <div class="plica-yo-resumen">
                    <span>{{ $miFila->participante->esEquipo ? 'Tu equipo va' : 'Vas' }} <strong>{{ $miFila->puesto }}º</strong> de {{ $grupo->filas->count() }}</span>
                    <span style="opacity:.7">·</span>
                    <span>{{ $grupo->sistema === \App\Models\Seccion::SISTEMA_PUESTOS || ($grupo->puntosParticipacion ?? 0) > 0 || ($grupo->puntosNoAsistencia ?? 0) !== 0
                        ? \App\Services\Scoring::pts($miFila->puntos)
                        : \App\Services\Scoring::valorRanking($grupo->criterio, $miFila->puntos) }}</span>
                </div>
            @endif
            @include('filament.partials.lista-clasificacion', ['grupo' => $grupo, 'modo' => 'temporada', 'socioId' => $socio?->id, 'limite' => 3])
            <div class="plica-mas">
                @if ($grupo->seccionId !== null && $club && $temporada)
                    @include('partials.compartir', [
                        'titulo' => 'Ranking '.$grupo->nombre.' · '.$club->nombre,
                        'texto' => \App\Services\Compartir::textoRanking($club, $temporada, $grupo),
                        'url' => route('club.seccion', ['club' => $club->slug, 'seccion' => $grupo->seccionSlug]),
                    ])
                    <x-filament::button tag="a" size="sm" icon="heroicon-o-table-cells" href="{{ \App\Filament\App\Pages\RankingSeccion::getUrl(['seccion' => $grupo->seccionId]) }}" wire:navigate>
                        Ver ranking completo
                    </x-filament::button>
                @endif
            </div>
        </x-filament::section>
    @endforeach

    @if ($rankingGrupos->isEmpty())
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy" />
                    Ranking
                </span>
            </x-slot>
            <p style="opacity:.7">Aún no hay mangas celebradas esta temporada.</p>
        </x-filament::section>
    @endif

    {{-- Última manga: el podio de cada sección; la clasificación completa, a un toque. --}}
    @if ($ultimaManga && $clasifGrupos->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                <span class="plica-h">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedScale" />
                    Última manga · {{ $ultimaManga->nombre }}
                </span>
            </x-slot>
            <x-slot name="description">{{ $ultimaManga->fecha->format('d/m/Y') }}{{ $ultimaManga->lugar ? ' · '.$ultimaManga->lugar : '' }}</x-slot>
            @foreach ($clasifGrupos as $grupo)
                <div style="margin-top:{{ $loop->first ? '0' : '1.2rem' }}; font-weight:700; font-size:.95rem; display:flex; align-items:center; gap:.5rem">
                    {{ $grupo->nombre }}
                    <span class="plica-etiqueta">por {{ mb_strtolower(\App\Models\Seccion::CRITERIOS[$grupo->criterio] ?? $grupo->criterio) }}</span>
                </div>
                @include('filament.partials.lista-clasificacion', ['grupo' => $grupo, 'modo' => 'manga', 'socioId' => $socio?->id, 'limite' => 3])
            @endforeach
            <div class="plica-mas">
                @if ($club)
                    @include('partials.compartir', [
                        'titulo' => $ultimaManga->nombre.' · '.$club->nombre,
                        'texto' => \App\Services\Compartir::textoManga($ultimaManga, $clasifGrupos),
                        'url' => $ultimaManga->urlPublica(),
                    ])
                @endif
                <x-filament::button tag="a" size="sm" icon="heroicon-o-scale" href="{{ \App\Filament\App\Pages\ClasificacionManga::getUrl(['manga' => $ultimaManga->id]) }}" wire:navigate>
                    Ver clasificación completa
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
