{{-- «Lleva Plica en el móvil»: instalar la app es cosa del navegador, así que aquí
     se explica cómo. En Android/Chrome sale el botón «Instalar» (beforeinstallprompt,
     capturado en la cabecera por si dispara antes que Alpine); en iPhone, el camino
     de Safari. No aparece si ya está instalada ni si el usuario lo cerró. --}}
<div x-data="{
        visible: false,
        ios: false,
        prompt: null,
        init() {
            const instalada = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            const cerrado = (() => { try { return localStorage.getItem('plica-instalar-cerrado') === '1'; } catch (e) { return false; } })();
            this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && ! window.MSStream;
            this.prompt = window.plicaInstallPrompt || null;
            window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); this.prompt = e; this.visible = ! instalada && ! cerrado; });
            this.visible = ! instalada && ! cerrado && (this.ios || this.prompt !== null);
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
        <template x-if="ios">
            <span>en Safari, toca <strong>Compartir</strong> y luego <strong>«Añadir a pantalla de inicio»</strong>.</span>
        </template>
        <template x-if="! ios">
            <span>toca <strong>Instalar</strong> y tendrás el icono en tu pantalla de inicio.</span>
        </template>
    </span>
    <template x-if="! ios && prompt">
        <button type="button" x-on:click="instalar()"
                style="min-height:2.4rem; padding:.4rem 1rem; border-radius:.7rem; background:rgb(16 185 129); color:#fff; font-weight:700">
            Instalar
        </button>
    </template>
    <button type="button" x-on:click="cerrar()" aria-label="Cerrar" title="No volver a enseñar"
            style="min-height:2.4rem; padding:.4rem .6rem; border-radius:.7rem; opacity:.6">✕</button>
</div>
