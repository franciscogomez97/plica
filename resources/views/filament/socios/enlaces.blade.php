{{-- «Dar acceso»: un enlace personal y de un solo uso por socio, para mandárselo a
     cada uno por WhatsApp (nunca a un grupo: con el enlace de otro entrarías como
     él). El botón abre WhatsApp directamente: con teléfono, en su chat; sin él,
     WhatsApp pide elegir el contacto. Primero los sin cuenta. Espera: $socios, $club. --}}
@php
    $filas = $socios
        ->sortBy(fn ($socio) => [$socio->user_id ? 1 : 0, mb_strtolower($socio->nombre)])
        ->map(fn ($socio) => ['socio' => $socio, 'texto' => $socio->mensajeAcceso(), 'wa' => $socio->urlWhatsAppAcceso(), 'directo' => $socio->numeroWhatsApp() !== null]);
    $sinCuenta = $socios->whereNull('user_id')->count();
    $conCuenta = $socios->count() - $sinCuenta;
    $sinTelefono = $socios->filter(fn ($socio) => $socio->numeroWhatsApp() === null)->count();
@endphp

<style>
    .enlaces-lista { display: flex; flex-direction: column; }
    .enlaces-fila { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .8rem; padding: .6rem 0; border-top: 1px solid rgba(128,128,128,.18); }
    .enlaces-fila:first-child { border-top: 0; }
    .enlaces-quien { flex: 1 1 10rem; min-width: 0; }
    .enlaces-nombre { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .enlaces-telefono { font-size: .8rem; opacity: .65; }
    .enlaces-estado { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .15rem .5rem; border-radius: 999px; background: rgba(128,128,128,.15); }
    .enlaces-estado.cuenta { background: rgba(16,185,129,.15); color: rgb(5 150 105); }
    .enlaces-btn { display: inline-flex; align-items: center; gap: .35rem; min-height: 2.2rem; padding: .35rem .75rem; border-radius: .6rem; border: 1px solid rgba(128,128,128,.35); font-size: .85rem; font-weight: 600; cursor: pointer; text-decoration: none; color: inherit; }
    .enlaces-btn.wa { background: #25d366; color: #fff; border-color: transparent; }
    .enlaces-resumen { font-size: .85rem; opacity: .75; margin-bottom: .6rem; }
</style>

<p class="enlaces-resumen">
    {{ $sinCuenta === 1 ? '1 socio sin cuenta' : "{$sinCuenta} socios sin cuenta" }}
    · {{ $conCuenta === 1 ? '1 con cuenta' : "{$conCuenta} con cuenta" }}
    (a estos el enlace les sirve para poner una contraseña nueva).
    @if ($sinTelefono > 0)
        {{ $sinTelefono === 1 ? 'A 1 le falta el teléfono' : "A {$sinTelefono} les falta el teléfono" }}: con él, WhatsApp se abre ya en su chat.
    @endif
</p>

<div class="enlaces-lista">
    @foreach ($filas as $f)
        <div class="enlaces-fila">
            <div class="enlaces-quien">
                <div class="enlaces-nombre">{{ $f['socio']->nombre }}</div>
                <div class="enlaces-telefono">{{ $f['directo'] ? $f['socio']->telefono : 'sin teléfono' }}</div>
            </div>
            <span class="enlaces-estado {{ $f['socio']->user_id ? 'cuenta' : '' }}">{{ $f['socio']->user_id ? 'Con cuenta' : 'Sin cuenta' }}</span>
            {{-- Siempre WhatsApp directo: con teléfono, en su chat; sin él, WhatsApp pide el contacto. --}}
            <a class="enlaces-btn wa" href="{{ $f['wa'] }}" target="_blank" rel="noopener">WhatsApp</a>
            <button type="button" class="enlaces-btn" data-texto="{{ $f['texto'] }}"
                    onclick="plicaCopiar(this, this.dataset.texto, 'Copiado ✓')">
                Copiar
            </button>
        </div>
    @endforeach
</div>
