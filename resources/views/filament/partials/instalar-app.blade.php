{{-- «Lleva Plica en el móvil»: instalar la app es cosa del navegador, así que aquí
     se explica cómo, según cuál sea:
       · iPhone/iPad: todos los navegadores son Safari por dentro y todos pueden
         «Añadir a pantalla de inicio»; cambia dónde está el botón Compartir
         (Safari abajo, Chrome arriba a la derecha, Firefox/Edge en el menú).
       · Android/Chrome y escritorio Chrome/Edge: botón «Instalar» (beforeinstallprompt,
         capturado en la cabecera por si dispara antes que Alpine).
       · Otros navegadores Android: el camino del menú.
     No aparece si ya está instalada ni si el usuario lo cerró. --}}
<div x-data="{
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
    x-show="visible" x-cloak
    style="display:flex; flex-wrap:wrap; align-items:center; gap:.6rem .9rem; padding:.8rem 1rem; border-radius:.9rem; background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.3); font-size:.92rem">
    <span style="flex:1 1 16rem; min-width:0">
        📲 <strong>Lleva Plica en el móvil</strong>, como una app:
        <template x-if="nav === 'safari'">
            <span>en Safari, toca <strong>Compartir</strong> (el cuadrado con la flecha, abajo) y luego <strong>«Añadir a pantalla de inicio»</strong>.</span>
        </template>
        <template x-if="nav === 'chrome-ios'">
            <span>en Chrome, toca <strong>Compartir</strong> (el cuadrado con la flecha, arriba a la derecha) y luego <strong>«Añadir a pantalla de inicio»</strong>.</span>
        </template>
        <template x-if="nav === 'menu-ios'">
            <span>abre el <strong>menú</strong> de tu navegador, toca <strong>Compartir</strong> y luego <strong>«Añadir a pantalla de inicio»</strong>.</span>
        </template>
        <template x-if="nav === 'android' && ! prompt">
            <span>abre el <strong>menú</strong> del navegador (⋮) y toca <strong>«Instalar aplicación»</strong> o <strong>«Añadir a pantalla de inicio»</strong>.</span>
        </template>
        <template x-if="prompt !== null">
            <span>toca <strong>Instalar</strong> y tendrás el icono en tu pantalla de inicio.</span>
        </template>
    </span>
    <template x-if="prompt !== null">
        <button type="button" x-on:click="instalar()"
                style="min-height:2.4rem; padding:.4rem 1rem; border-radius:.7rem; background:rgb(16 185 129); color:#fff; font-weight:700">
            Instalar
        </button>
    </template>
    <button type="button" x-on:click="cerrar()" aria-label="Cerrar" title="No volver a enseñar"
            style="min-height:2.4rem; padding:.4rem .6rem; border-radius:.7rem; opacity:.6">✕</button>
</div>
