{{-- Botón «Compartir imagen»: la tarjeta del podio (JPEG 1080×1920) generada
     por App\Services\Podio. En el móvil, adjunta la imagen a la hoja de
     compartir del sistema (WhatsApp, etc.) con la API Web Share; donde no se
     puede (escritorio, navegadores viejos), abre/descarga la imagen. Sin
     dependencias. Espera: $url (de la imagen), $titulo; opcionales $compacto y
     $pie (texto que viaja con la imagen: el podio escrito y el enlace, para que
     sea un solo envío; en iPhone WhatsApp puede ignorarlo). --}}
@php $compacto ??= false; $pie ??= null; $id = 'ci'.substr(md5($url.$pie), 0, 8); @endphp
<a href="{{ $url }}"
   id="{{ $id }}"
   @if ($pie) data-pie="{{ $pie }}" @endif
   download="{{ \Illuminate\Support\Str::slug($titulo) }}.jpg"
   target="_blank"
   rel="noopener"
   title="{{ $titulo }} · imagen del podio"
   @if ($compacto)
   style="display:inline-flex; align-items:center; gap:.4rem; padding:.4rem .75rem; border-radius:.6rem; background:#fff; color:#0f172a; border:1px solid #cbd5e1; font-weight:600; font-size:.85rem; line-height:1.2; cursor:pointer; text-decoration:none; white-space:nowrap"
   @else
   style="display:inline-flex; align-items:center; gap:.5rem; padding:.6rem 1rem; border-radius:.75rem; background:#0f172a; color:#fff; font-weight:700; font-size:.95rem; line-height:1.2; box-shadow:0 1px 2px rgba(0,0,0,.15); cursor:pointer; text-decoration:none"
   @endif>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:{{ $compacto ? '1.1rem' : '1.2rem' }}; height:{{ $compacto ? '1.1rem' : '1.2rem' }}; flex:none">
        <rect x="3" y="3" width="18" height="18" rx="3" /><circle cx="9" cy="9" r="2" /><path d="M21 15l-5-5L5 21" />
    </svg>
    {{ $etiqueta ?? ($compacto ? 'Imagen' : 'Compartir imagen') }}
</a>
<script>
(function () {
    var a = document.getElementById(@json($id));
    if (!a || !navigator.share || !navigator.canShare) return;
    a.addEventListener('click', function (e) {
        e.preventDefault();
        fetch(a.href).then(function (r) { return r.blob(); }).then(function (b) {
            var f = new File([b], a.getAttribute('download'), { type: 'image/jpeg' });
            var datos = { files: [f], title: a.title };
            if (a.dataset.pie) datos.text = a.dataset.pie;
            if (navigator.canShare({ files: [f] })) return navigator.share(datos);
            window.open(a.href, '_blank');
        }).catch(function () { window.open(a.href, '_blank'); });
    });
})();
</script>
