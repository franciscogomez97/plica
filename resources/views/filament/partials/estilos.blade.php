{{-- Estilos comunes de clasificaciones/rankings en los paneles Filament --}}
<style>
    /* Cabeceras de sección con icono */
    .plica-h { display: inline-flex; align-items: center; gap: .5rem; }
    .plica-h svg { width: 1.25rem; height: 1.25rem; color: rgb(16, 185, 129); flex: none; }

    /* Listas de clasificación (móvil primero; también en escritorio) */
    .plica-lista { display: flex; flex-direction: column; }
    .plica-fila { display: flex; align-items: center; gap: .8rem; padding: .8rem .35rem; border-top: 1px solid rgba(128, 128, 128, .18); min-height: 3.25rem; position: relative; }
    .plica-fila:first-child { border-top: none; }
    .plica-yo { background: rgba(16, 185, 129, .1); }
    .plica-fila.plica-yo { border-radius: .6rem; padding-left: .7rem; padding-right: .7rem; }
    .plica-fila.plica-lider .plica-nombre { font-size: 1.08rem; }
    .plica-pos { flex: none; width: 2.4rem; height: 2.4rem; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .95rem; background: rgba(128, 128, 128, .14); }
    /* Podio: oro, plata y bronce, igual que en el cuadro manga a manga. */
    .plica-pos.p1 { background: #fbbf24; color: #451a03; box-shadow: 0 0 0 3px rgba(251, 191, 36, .25); }
    .plica-pos.p2 { background: #d4d4d8; color: #27272a; }
    .plica-pos.p3 { background: #d97706; color: #fff; }
    .plica-quien { flex: 1; min-width: 0; }
    .plica-nombre { font-size: 1rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .plica-detalle { font-size: .8rem; opacity: .6; }
    .plica-yo .plica-detalle { opacity: .8; }
    .plica-valor { flex: none; text-align: right; font-size: 1.05rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .plica-valor small { display: block; font-size: .72rem; font-weight: 500; opacity: .6; }
    /* Barra de distancia con el líder: se ve de un vistazo cuánto falta. */
    .plica-barra { height: .3rem; margin-top: .4rem; border-radius: 999px; background: rgba(128, 128, 128, .12); overflow: hidden; }
    .plica-barra span { display: block; height: 100%; border-radius: 999px; background: rgb(16 185 129); opacity: .55; }
    .plica-lider .plica-barra span { opacity: 1; }
    .plica-salto { padding: .2rem .35rem .2rem 3.55rem; font-size: .8rem; opacity: .5; border-top: 1px solid rgba(128, 128, 128, .18); }
    .plica-mas { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: .6rem; margin-top: .9rem; }
    .plica-mayor { margin-top: .8rem; padding: .6rem .8rem; border-radius: .6rem; background: rgba(251, 191, 36, .14); font-size: .9rem; }
    .plica-etiqueta { display: inline-flex; align-items: center; padding: .15rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; background: rgba(16, 185, 129, .14); color: rgb(5 150 105); }
</style>
