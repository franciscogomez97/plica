{{-- Modal «Dar acceso a {socio}»: lo primero y más grande, mandárselo por WhatsApp;
     debajo, el mensaje para copiarlo si se prefiere; al final, qué pasa después.
     Estilos propios: el panel no lleva las utilidades de Tailwind. Espera: $url, $socio. --}}
@php
    $mensaje = $socio->mensajeAcceso();
    $directo = $socio->numeroWhatsApp() !== null;
    $conCuenta = $socio->user_id !== null;
@endphp

<style>
    .acceso { display: flex; flex-direction: column; gap: 1.1rem; }
    .acceso-wa { display: flex; align-items: center; gap: .9rem; padding: 1rem 1.1rem; border-radius: 1rem; background: #25d366; color: #fff; text-decoration: none; box-shadow: 0 1px 3px rgba(0,0,0,.18); }
    .acceso-wa:hover { background: #1fbe5a; }
    .acceso-wa:focus-visible { outline: 3px solid #0f766e; outline-offset: 2px; }
    .acceso-wa svg { flex: none; width: 2rem; height: 2rem; }
    .acceso-wa-titulo { font-size: 1.1rem; font-weight: 800; line-height: 1.25; }
    .acceso-wa-sub { display: block; margin-top: .15rem; font-size: .85rem; line-height: 1.35; opacity: .92; }
    .acceso-o { display: flex; align-items: center; gap: .75rem; font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; opacity: .55; }
    .acceso-o::before, .acceso-o::after { content: ""; flex: 1; height: 1px; background: currentColor; opacity: .35; }
    .acceso-caja { border: 1px solid rgba(128,128,128,.3); border-radius: .9rem; overflow: hidden; }
    .acceso-caja-cabecera { padding: .55rem .85rem; font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; opacity: .7; background: rgba(128,128,128,.08); }
    .acceso-mensaje { margin: 0; padding: .85rem; font-size: .92rem; line-height: 1.5; white-space: pre-wrap; word-break: break-word; font-family: inherit; }
    .acceso-enlace { display: block; width: 100%; box-sizing: border-box; padding: .7rem .85rem; border: 0; border-top: 1px solid rgba(128,128,128,.25); background: transparent; color: inherit; font-size: .85rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .acceso-botones { display: flex; flex-wrap: wrap; gap: .5rem; padding: .7rem .85rem; border-top: 1px solid rgba(128,128,128,.25); }
    .acceso-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; min-height: 2.6rem; padding: .5rem .9rem; border-radius: .7rem; border: 1px solid rgba(128,128,128,.45); background: transparent; color: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; }
    .acceso-btn:hover { background: rgba(128,128,128,.12); }
    .acceso-btn:focus-visible { outline: 3px solid #10b981; outline-offset: 2px; }
    .acceso-pasos { margin: 0; padding: 0 0 0 1.25rem; list-style: decimal; font-size: .88rem; line-height: 1.5; opacity: .85; }
    .acceso-pasos li + li { margin-top: .25rem; }
    .acceso-nota { margin: 0; font-size: .82rem; line-height: 1.45; opacity: .7; }
</style>

<div class="acceso">
    <a class="acceso-wa" href="{{ $socio->urlWhatsAppAcceso() }}" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm0 18.15c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 4.54 0 8.24 3.7 8.24 8.24 0 4.55-3.7 8.24-8.24 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.78.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.22-.16-.47-.28z" />
        </svg>
        <span>
            <span class="acceso-wa-titulo">{{ $directo ? 'Enviar por WhatsApp a '.$socio->telefono : 'Enviar por WhatsApp' }}</span>
            <span class="acceso-wa-sub">
                @if ($directo)
                    Se abre su chat con el mensaje y el enlace ya escritos. Solo tienes que darle a enviar.
                @else
                    Se abre WhatsApp con el mensaje escrito; eliges a {{ $socio->nombre }} y envías. Sin teléfono en su ficha: si se lo pones, se abre directamente su chat.
                @endif
            </span>
        </span>
    </a>

    <div class="acceso-o">o cópialo y mándaselo tú</div>

    <div class="acceso-caja">
        <div class="acceso-caja-cabecera">Mensaje para {{ $socio->nombre }}</div>
        <pre class="acceso-mensaje">{{ $mensaje }}</pre>
        <input type="text" readonly value="{{ $url }}" aria-label="Enlace de acceso" class="acceso-enlace" onclick="this.select()">
        <div class="acceso-botones">
            <button type="button" class="acceso-btn" onclick="plicaCopiar(this, @js($mensaje), 'Mensaje copiado ✓')">Copiar mensaje</button>
            <button type="button" class="acceso-btn" onclick="plicaCopiar(this, @js($url), 'Enlace copiado ✓')">Copiar solo el enlace</button>
        </div>
    </div>

    <ol class="acceso-pasos">
        <li>{{ $socio->nombre }} abre el enlace en su móvil.</li>
        @if ($conCuenta)
            <li>Pone una contraseña nueva a su cuenta.</li>
            <li>Entra en Plica con su email y esa contraseña, como siempre.</li>
        @else
            <li>Crea su cuenta: su nombre, su email y una contraseña.</li>
            <li>Ya está dentro: ve los rankings, las clasificaciones y las próximas mangas.</li>
        @endif
    </ol>

    <p class="acceso-nota">🔒 Enlace personal y de un solo uso: en cuanto lo abra, deja de funcionar. Si lo pierde, vuelve aquí y sale otro nuevo.</p>
</div>
