<div class="space-y-2">
    <input
        type="text"
        readonly
        value="{{ $url }}"
        onclick="this.select()"
        class="w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
    />
    <button
        type="button"
        onclick="navigator.clipboard.writeText(@js($url)); this.innerText = '¡Copiado!';"
        class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white"
    >
        Copiar link
    </button>
</div>
