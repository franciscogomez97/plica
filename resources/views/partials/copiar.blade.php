{{-- Copiar al portapapeles que funciona también sin HTTPS: navigator.clipboard solo
     existe en contexto seguro (https o localhost); por IP y http es undefined y el
     botón decía «✔» sin copiar nada. Fallback con execCommand y, si tampoco, se
     dice claro que hay que copiarlo a mano. --}}
<script>
    window.plicaCopiar = function (boton, texto, ok) {
        var listo = function () { boton.textContent = ok || 'Copiado ✓'; };
        var aMano = function () { boton.textContent = 'No se ha podido copiar: selecciónalo y copia a mano'; };
        if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(listo).catch(aMano);
            return;
        }
        try {
            var area = document.createElement('textarea');
            area.value = texto;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.top = '0';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.focus();
            area.select();
            area.setSelectionRange(0, texto.length);
            var hecho = document.execCommand('copy');
            document.body.removeChild(area);
            hecho ? listo() : aMano();
        } catch (e) {
            aMano();
        }
    };
</script>
