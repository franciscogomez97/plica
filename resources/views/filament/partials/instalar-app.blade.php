{{-- «Lleva Plica en el móvil»: instalar la app es cosa del navegador, así que aquí
     se explica cómo, según cuál sea:
       · iPhone/iPad: todos los navegadores son Safari por dentro y todos pueden
         «Añadir a pantalla de inicio»; cambia dónde está el botón Compartir
         (Safari abajo, Chrome arriba a la derecha, Firefox/Edge en el menú).
       · Android/Chrome y escritorio Chrome/Edge: botón «Instalar» (beforeinstallprompt,
         capturado en la cabecera por si dispara antes que Alpine).
       · Otros navegadores Android: el camino del menú.
     No aparece si ya está instalada ni si el usuario lo cerró. --}}
<style>
    .plica-instalar { position: relative; display: flex; gap: .9rem; align-items: flex-start; padding: 1rem 2.75rem 1rem 1rem; border-radius: 1rem; background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.3); }
    .plica-instalar-icono { flex: none; width: 2.75rem; height: 2.75rem; border-radius: .8rem; background: #fff; padding: .35rem; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
    .plica-instalar-titulo { font-weight: 800; font-size: 1rem; line-height: 1.3; }
    .plica-instalar-texto { margin-top: .2rem; font-size: .9rem; line-height: 1.45; opacity: .8; }
    .plica-instalar-texto strong { opacity: 1; }
    .plica-instalar-boton { display: inline-flex; align-items: center; gap: .45rem; margin-top: .7rem; min-height: 2.6rem; padding: .5rem 1.1rem; border-radius: .75rem; background: rgb(16 185 129); color: #fff; font-weight: 700; font-size: .95rem; box-shadow: 0 1px 2px rgba(0,0,0,.12); }
    .plica-instalar-boton svg { width: 1.15rem; height: 1.15rem; }
    .plica-instalar-cerrar { position: absolute; top: .55rem; right: .55rem; width: 2rem; height: 2rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; opacity: .55; }
    .plica-instalar-cerrar:hover { opacity: 1; background: rgba(128,128,128,.15); }
    .plica-instalar-cerrar svg { width: 1.1rem; height: 1.1rem; }
</style>
<div class="plica-instalar"
    x-data="{
        visible: false,
        nav: 'otro',
        prompt: null,
        init() {
            const ua = navigator.userAgent;
            const ios = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            const android = /android/i.test(ua);
            if (ios) {
                this.nav = /CriOS/i.test(ua) ? 'chrome-ios' : (/FxiOS|EdgiOS/i.test(ua) ? 'menu-ios' : 'safari');
            } else if (android) {
                this.nav = 'android';
            }
            const instalada = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            const cerrado = (() => { try { return localStorage.getItem('plica-instalar-cerrado') === '1'; } catch (e) { return false; } })();
            this.prompt = window.plicaInstallPrompt || null;
            const decidir = () => { this.visible = ! instalada && ! cerrado && (ios || android || this.prompt !== null); };
            window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); this.prompt = e; decidir(); });
            decidir();
        },
        async instalar() {
            if (! this.prompt) return;
            this.prompt.prompt();
            const { outcome } = await this.prompt.userChoice;
            if (outcome === 'accepted') this.visible = false;
        },
        cerrar() {
            this.visible = false;
            try { localStorage.setItem('plica-instalar-cerrado', '1'); } catch (e) {}
        },
    }"
    x-show="visible" x-cloak>
    <button type="button" class="plica-instalar-cerrar" x-on:click="cerrar()" aria-label="Cerrar" title="No volver a enseñar">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
    </button>
    <img src="{{ asset(\App\Support\Marca::LOGO) }}" alt="" class="plica-instalar-icono">
    <div style="min-width:0; flex:1">
        <div class="plica-instalar-titulo">Lleva Plica en el móvil, como una app</div>
        <div class="plica-instalar-texto">
            <template x-if="nav === 'safari' && prompt === null">
                <span>En Safari, toca <strong>Compartir</strong> (el cuadrado con la flecha, abajo) y luego <strong>«Añadir a pantalla de inicio»</strong>.</span>
            </template>
            <template x-if="nav === 'chrome-ios' && prompt === null">
                <span>En Chrome, toca <strong>Compartir</strong> (el cuadrado con la flecha, arriba a la derecha) y luego <strong>«Añadir a pantalla de inicio»</strong>.</span>
            </template>
            <template x-if="nav === 'menu-ios' && prompt === null">
                <span>Abre el <strong>menú</strong> de tu navegador, toca <strong>Compartir</strong> y luego <strong>«Añadir a pantalla de inicio»</strong>.</span>
            </template>
            <template x-if="nav === 'android' && prompt === null">
                <span>Abre el <strong>menú</strong> del navegador (⋮) y toca <strong>«Instalar aplicación»</strong> o <strong>«Añadir a pantalla de inicio»</strong>.</span>
            </template>
            <template x-if="prompt !== null">
                <span>Tendrás el icono en tu pantalla de inicio y se abrirá a pantalla completa, sin barra del navegador.</span>
            </template>
        </div>
        <template x-if="prompt !== null">
            <button type="button" class="plica-instalar-boton" x-on:click="instalar()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Instalar Plica
            </button>
        </template>
    </div>
</div>
