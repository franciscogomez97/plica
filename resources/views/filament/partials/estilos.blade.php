{{-- Estilos comunes de clasificaciones/rankings en los paneles Filament --}}
<style>
    /* Cabeceras de sección con icono */
    .plica-h { display: inline-flex; align-items: center; gap: .5rem; }
    .plica-h svg { width: 1.25rem; height: 1.25rem; color: rgb(16, 185, 129); flex: none; }

    /* Listas de clasificación (móvil primero; también en escritorio) */
    .plica-lista { display: flex; flex-direction: column; }
    .plica-fila { display: flex; align-items: center; gap: .8rem; padding: .8rem .35rem; border-top: 1px solid rgba(128, 128, 128, .18); min-height: 3.25rem; }
    .plica-fila:first-child { border-top: none; }
    .plica-yo { background: rgba(16, 185, 129, .1); }
    .plica-fila.plica-yo { border-radius: .6rem; padding-left: .7rem; padding-right: .7rem; }
    .plica-pos { flex: none; width: 2.4rem; height: 2.4rem; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; background: rgba(128, 128, 128, .14); }
    .plica-pos-podio { background: rgba(16, 185, 129, .2); color: rgb(16, 185, 129); }
    .plica-quien { flex: 1; min-width: 0; }
    .plica-nombre { font-size: 1rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .plica-detalle { font-size: .8rem; opacity: .6; }
    .plica-valor { flex: none; text-align: right; font-size: 1.05rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .plica-valor small { display: block; font-size: .72rem; font-weight: 500; opacity: .6; }
</style>
