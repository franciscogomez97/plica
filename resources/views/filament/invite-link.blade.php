@php
    $mensaje = $socio->mensajeAcceso();
@endphp

<div class="space-y-3">
    <a
        href="https://wa.me/?text={{ rawurlencode($mensaje) }}"
        target="_blank"
        rel="noopener"
        class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-base font-semibold text-white hover:bg-emerald-500"
    >
        💬 Enviar por WhatsApp
    </a>

    <div class="flex items-center gap-2">
        <input
            type="text"
            readonly
            value="{{ $url }}"
            onclick="this.select()"
            class="w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
        />
        <button
            type="button"
            onclick="plicaCopiar(this, @js($url), 'Copiado ✓')"
            class="shrink-0 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium dark:border-gray-600"
        >
            Copiar
        </button>
    </div>
    <p class="text-xs" style="opacity:.6">De un solo uso: al usarlo, deja de funcionar.</p>
</div>
