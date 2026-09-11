{{-- Flecha «Atrás» de las páginas interiores. Va SIEMPRE a la página «padre»
     (listado, pesaje, inicio), nunca al historial del navegador: el historial
     devuelve un formulario recién enviado con los datos puestos, y se acaba
     creando dos veces lo mismo. El destino lo decide AdminPanelProvider. --}}
<a href="{{ $url }}" wire:navigate
   style="display:inline-flex; align-items:center; gap:.45rem; padding:.4rem .75rem .4rem 0; font-weight:600; font-size:.95rem; opacity:.75">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true" style="width:1.25rem; height:1.25rem; flex:none">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
    </svg>
    Atrás
</a>
