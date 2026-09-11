{{-- Enlaces de acceso para todos los socios activos: un mensaje por socio, listo
     para pegar en WhatsApp. Espera: $socios, $club. --}}
@php
    $mensajes = $socios->map(function ($socio) use ($club) {
        $url = $socio->accessUrl();
        $texto = $socio->user_id
            ? "Hola {$socio->nombre} 👋 Este es tu acceso a Plica, la app de {$club->nombre}: {$url}\nAl abrirlo eliges una contraseña nueva. Es de un solo uso."
            : "Hola {$socio->nombre} 👋 Este es tu acceso a Plica, la app de {$club->nombre}: {$url}\nAl abrirlo creas tu cuenta y ves los rankings, las clasificaciones y las próximas mangas. Es de un solo uso.";

        return ['socio' => $socio, 'texto' => $texto];
    });
@endphp

<style>
    .enlaces-lista { display: flex; flex-direction: column; }
    .enlaces-fila { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .8rem; padding: .6rem 0; border-top: 1px solid rgba(128,128,128,.18); }
    .enlaces-fila:first-child { border-top: 0; }
    .enlaces-nombre { flex: 1 1 10rem; min-width: 0; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .enlaces-estado { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .15rem .5rem; border-radius: 999px; background: rgba(128,128,128,.15); }
    .enlaces-estado.cuenta { background: rgba(16,185,129,.15); color: rgb(5 150 105); }
    .enlaces-btn { display: inline-flex; align-items: center; gap: .35rem; min-height: 2.2rem; padding: .35rem .75rem; border-radius: .6rem; border: 1px solid rgba(128,128,128,.35); font-size: .85rem; font-weight: 600; cursor: pointer; }
    .enlaces-btn.wa { background: #25d366; color: #fff; border-color: transparent; }
    .enlaces-todos { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; margin-bottom: .9rem; }
</style>

<div class="enlaces-todos">
    <button type="button" class="enlaces-btn" data-texto="{{ $mensajes->pluck('texto')->implode("\n\n") }}"
            onclick="navigator.clipboard.writeText(this.dataset.texto).then(() => { this.textContent = 'Copiados ✓'; })">
        Copiar todos los mensajes
    </button>
    <span style="font-size:.85rem; opacity:.7">{{ $socios->count() === 1 ? '1 socio activo' : $socios->count().' socios activos' }}</span>
</div>

<div class="enlaces-lista">
    @foreach ($mensajes as $m)
        <div class="enlaces-fila">
            <div class="enlaces-nombre">{{ $m['socio']->nombre }}</div>
            <span class="enlaces-estado {{ $m['socio']->user_id ? 'cuenta' : '' }}">{{ $m['socio']->user_id ? 'Con cuenta' : 'Sin cuenta' }}</span>
            <button type="button" class="enlaces-btn" data-texto="{{ $m['texto'] }}"
                    onclick="navigator.clipboard.writeText(this.dataset.texto).then(() => { this.textContent = 'Copiado ✓'; })">
                Copiar
            </button>
            <button type="button" class="enlaces-btn wa" data-texto="{{ $m['texto'] }}" data-titulo="Acceso a Plica"
                    onclick="(function (b) {
                        if (navigator.share) { navigator.share({ title: b.dataset.titulo, text: b.dataset.texto }).catch(function () {}); }
                        else { window.open('https://wa.me/?text=' + encodeURIComponent(b.dataset.texto), '_blank', 'noopener'); }
                    })(this)">
                WhatsApp
            </button>
        </div>
    @endforeach
</div>
