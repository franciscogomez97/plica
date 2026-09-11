@extends('layouts.public')

@section('title', 'Plica — la app de tu club de pesca')

@section('content')
    @php
        // El club de ejemplo de la landing: el de pruebas en producción, el de demo en desarrollo.
        $demo = \App\Models\Club::whereIn('slug', ['club-de-pruebas', 'cd-pesca-piloto'])->where('perfil_publico', true)->first();
        $plan = config('plica.plan');
        $gratisHasta = \Illuminate\Support\Carbon::parse($plan['gratis_hasta'])->locale('es');
        $anioOtono = $gratisHasta->year - 1;
        // El número no va en el HTML (los bots rastrean teléfonos): /whatsapp redirige al pulsar.
        $whatsapp = config('plica.whatsapp') ? route('whatsapp') : null;
    @endphp

    {{-- Portada --}}
    <section class="py-8 text-center sm:py-14">
        <img src="{{ asset(\App\Support\Marca::LOGO) }}" alt="Plica" class="mx-auto size-20 sm:size-24">
        <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
            Tu club de pesca, <span class="text-emerald-400">sin Excel y sin líos de WhatsApp</span>
        </h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-400">
            Mangas, pesajes, clasificaciones y ranking de temporada, calculados solos y con las reglas de tu club.
            El admin mete las plicas en tres minutos desde el móvil; los socios lo ven al momento.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="#solicitar" class="inline-flex min-h-12 items-center rounded-xl bg-emerald-600 px-6 text-base font-bold text-white hover:bg-emerald-500">Solicita acceso para tu club</a>
            @if ($demo)
                <a href="{{ route('club.publico', $demo) }}" class="inline-flex min-h-12 items-center rounded-xl border border-slate-700 px-6 text-base font-semibold text-slate-200 hover:border-emerald-500 hover:text-emerald-300">Ver un club de ejemplo →</a>
            @endif
        </div>
        <p class="mt-4 text-sm text-slate-500">Te lo montamos nosotros y te atendemos por WhatsApp, sin reuniones.</p>
        @if ($whatsapp)
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="mt-3 inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#25d366] px-5 text-base font-bold text-white hover:brightness-110">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-5"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm0 18.15c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 4.54 0 8.24 3.7 8.24 8.24 0 4.55-3.7 8.24-8.24 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.78.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.22-.16-.47-.28z"/></svg>
                Escríbenos por WhatsApp
            </a>
        @endif
    </section>

    {{-- Cómo funciona: el ciclo real de una manga --}}
    <section class="py-10">
        <h2 class="text-center text-2xl font-extrabold tracking-tight sm:text-3xl">Así es una manga con Plica</h2>
        <p class="mx-auto mt-2 max-w-2xl text-center text-slate-400">El ritual del agua no se toca: las plicas siguen en papel. Plica digitaliza el antes y el después.</p>
        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <div class="text-sm font-bold uppercase tracking-wide text-emerald-400">Antes</div>
                <h3 class="mt-1 text-lg font-bold">Convocas por WhatsApp</h3>
                <p class="mt-2 text-sm text-slate-400">Fecha, lugar y «cómo llegar» con el texto ya escrito. Los socios tocan «Asistiré» y tú sabes con quién contar.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <div class="text-sm font-bold uppercase tracking-wide text-emerald-400">El día</div>
                <h3 class="mt-1 text-lg font-bold">Pesas en tres minutos</h3>
                <p class="mt-2 text-sm text-slate-400">Una fila por socio: piezas, peso y pieza mayor. Cada casilla se guarda sola. Sin formularios, sin fórmulas, sin errores de suma.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <div class="text-sm font-bold uppercase tracking-wide text-emerald-400">Después</div>
                <h3 class="mt-1 text-lg font-bold">Compartes y ya está</h3>
                <p class="mt-2 text-sm text-slate-400">Clasificación de la manga y ranking de temporada al instante, con un enlace que cualquiera puede abrir. Un botón y al grupo.</p>
            </div>
        </div>
    </section>

    {{-- Lo que hace: empezando por lo que el club ya tiene --}}
    <section class="py-10">
        <h2 class="text-center text-2xl font-extrabold tracking-tight sm:text-3xl">Hecho para clubes de pesca</h2>
        <p class="mx-auto mt-2 max-w-2xl text-center text-slate-400">
            Todo lo que hoy lleváis en un Excel y en el grupo de WhatsApp, en su sitio y calculado solo. Y no empezáis de cero: nos pasáis lo que ya tenéis.
        </p>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-emerald-800/60 bg-emerald-950/40 p-5 sm:col-span-2 lg:col-span-1">
                <h3 class="font-bold text-emerald-400">Empieza con lo que ya tienes</h3>
                <p class="mt-2 text-sm text-slate-300">Mándanos por WhatsApp vuestro Excel o la lista de socios, tal cual estén. Os montamos el club en cuestión de minutos: secciones con sus reglas, socios y calendario. Y si queréis, cargamos las mangas ya celebradas para que el ranking salga entero desde el primer día.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h3 class="font-bold text-emerald-400">Secciones con sus reglas</h3>
                <p class="mt-2 text-sm text-slate-400">Orilla, embarcación, pato, black bass, carpfishing… Cada sección compite por peso, medida o piezas, con sus descartes, sus puntos por asistencia y su desempate. Las reglas de vuestro reglamento, no las de un programa.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h3 class="font-bold text-emerald-400">Manga a manga</h3>
                <p class="mt-2 text-sm text-slate-400">Un cuadro tipo hoja de cálculo: pescadores en filas, mangas en columnas, quién ganó cada una, quién hizo la pieza mayor y quién va ganando. Como vuestro Excel, pero solo y sin errores.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h3 class="font-bold text-emerald-400">Socios sin registros</h3>
                <p class="mt-2 text-sm text-slate-400">Cada socio recibe su enlace de acceso por WhatsApp y entra con un toque. Sin formularios de registro ni contraseñas que olvidar. Y quien no tenga cuenta ve igualmente las clasificaciones que compartáis.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h3 class="font-bold text-emerald-400">Página pública del club</h3>
                <p class="mt-2 text-sm text-slate-400">Vuestro escudo, el calendario, los rankings y la última manga en una dirección que puedes mandar a cualquiera. Con vista previa bonita en WhatsApp.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h3 class="font-bold text-emerald-400">Desde el móvil, como una app</h3>
                <p class="mt-2 text-sm text-slate-400">Pensado para el admin que gestiona desde el embalse y para el socio que mira el ranking el domingo por la tarde. Se instala en la pantalla de inicio.</p>
            </div>
        </div>
    </section>

    {{-- Precio: oculto de momento (decisión de septiembre de 2026). Para volver a enseñarlo, quitar este comentario.
    
    <section class="py-10">
        <div class="mx-auto max-w-2xl rounded-3xl border border-emerald-900/60 bg-emerald-950/40 p-8 text-center">
            <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Un precio que se aprueba en asamblea</h2>
            <p class="mt-6 text-5xl font-extrabold tracking-tight">{{ $plan['precio'] }} €<span class="text-xl font-semibold text-slate-400"> / temporada</span></p>
            <p class="mt-2 text-sm text-slate-400">IVA incluido. Una factura al año, en enero, cuando el club cobra las cuotas. La primera, en enero de {{ $gratisHasta->year }}, por la temporada {{ $gratisHasta->year }}.</p>
            <ul class="mx-auto mt-6 max-w-md space-y-2 text-left text-sm text-slate-300">
                <li class="flex gap-2"><span class="text-emerald-400">✓</span> Gratis hasta el {{ $gratisHasta->isoFormat('D [de] MMMM [de] YYYY') }} para los clubes que entren en el otoño de {{ $anioOtono }}.</li>
                <li class="flex gap-2"><span class="text-emerald-400">✓</span> Los diez primeros clubes: {{ $plan['fundadores'] }} € por temporada, para siempre.</li>
                <li class="flex gap-2"><span class="text-emerald-400">✓</span> Alta acompañada por WhatsApp: nos pasas tus secciones y tu lista de socios y te lo dejamos montado.</li>
                <li class="flex gap-2"><span class="text-emerald-400">✓</span> Sin permanencia. Si el club no renueva, los datos se conservan en solo lectura; nunca se borran.</li>
            </ul>
            <p class="mt-6 text-sm text-slate-500">Menos que los trofeos de una manga. Menos de 2 € por socio y año en un club de 80.</p>
        </div>
    </section>
    --}}

    {{-- Preguntas --}}
    <section class="py-10">
        <h2 class="text-center text-2xl font-extrabold tracking-tight sm:text-3xl">Lo que preguntan los presidentes</h2>
        <div class="mx-auto mt-8 max-w-3xl divide-y divide-slate-800 rounded-2xl border border-slate-800 bg-slate-900">
            <details class="group p-5">
                <summary class="cursor-pointer list-none font-semibold">¿Mi reglamento es distinto?
                    <span class="float-right text-slate-500 group-open:rotate-45">+</span></summary>
                <p class="mt-2 text-sm text-slate-400">Cada sección se configura con sus reglas: por peso, medida o piezas; descartes; puntos por asistencia; qué decide un empate. Y la app te lo explica en una frase para que compruebes que es lo vuestro. Si falta algo que un club real necesite, lo añadimos.</p>
            </details>
            <details class="group p-5">
                <summary class="cursor-pointer list-none font-semibold">¿Los socios necesitan cuenta?
                    <span class="float-right text-slate-500 group-open:rotate-45">+</span></summary>
                <p class="mt-2 text-sm text-slate-400">Para ver las clasificaciones que compartes por WhatsApp, no: son enlaces públicos. Para decir «asistiré» y ver su puesto resaltado, sí; la crean con un toque desde el enlace que les mandas.</p>
            </details>
            <details class="group p-5">
                <summary class="cursor-pointer list-none font-semibold">¿Y si nos apañamos con Excel?
                    <span class="float-right text-slate-500 group-open:rotate-45">+</span></summary>
                <p class="mt-2 text-sm text-slate-400">Seguid con él si os funciona. Plica es para el club que cada domingo pierde una hora sumando, discute un descarte o tiene que reenviar el ranking cuatro veces. Pruébalo con una manga real: si no ahorra tiempo, no hay nada que pagar.</p>
            </details>
            <details class="group p-5">
                <summary class="cursor-pointer list-none font-semibold">¿Qué pasa con los datos?
                    <span class="float-right text-slate-500 group-open:rotate-45">+</span></summary>
                <p class="mt-2 text-sm text-slate-400">Guardamos lo mínimo: nombre, email si lo hay y resultados. Servidores en la Unión Europea, copias diarias, y el club es siempre el dueño de sus datos. Está todo en la <a href="{{ route('legal.privacidad') }}" class="text-emerald-400 hover:underline">política de privacidad</a>.</p>
            </details>
            <details class="group p-5">
                <summary class="cursor-pointer list-none font-semibold">¿Cuánto tarda el alta?
                    <span class="float-right text-slate-500 group-open:rotate-45">+</span></summary>
                <p class="mt-2 text-sm text-slate-400">Normalmente el mismo día. Nos mandas el nombre del club y el email del que será administrador, y te llega el acceso. El resto lo resolvemos por WhatsApp: secciones, socios y la primera manga, sin reuniones ni tutoriales.</p>
            </details>
        </div>
    </section>

    {{-- Solicitud --}}
    <section id="solicitar" class="mx-auto max-w-lg py-10">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="text-xl font-bold">Solicita acceso para tu club</h2>
            <p class="mt-1 text-sm text-slate-400">
                Sin registros ni configuraciones: cuéntanos tu club y <span class="text-slate-200">te lo dejamos montado nosotros</span>.
                Recibirás tu acceso de administrador listo para entrar, normalmente el mismo día.
            </p>

            @if (session('solicitud_ok'))
                <div class="mt-4 rounded-lg border border-emerald-700 bg-emerald-950 p-3 text-sm text-emerald-300">
                    ¡Recibido! Te montamos el club y te enviamos tu acceso de administrador a ese email — normalmente el mismo día.
                </div>
            @else
                <form method="POST" action="{{ route('solicitud.store') }}" class="mt-4 space-y-3">
                    @csrf
                    {{-- Trampas para bots (App\Services\AntiSpam): un campo que las personas no ven y el momento en que se pintó el formulario. --}}
                    <input type="hidden" name="{{ \App\Services\AntiSpam::CAMPO_SELLO }}" value="{{ \App\Services\AntiSpam::sello() }}">
                    <div aria-hidden="true" style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden">
                        <label for="web">Web</label>
                        <input id="web" name="{{ \App\Services\AntiSpam::CAMPO_TRAMPA }}" type="text" tabindex="-1" autocomplete="off" value="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-300" for="club_nombre">Nombre del club</label>
                        <input id="club_nombre" name="club_nombre" required maxlength="120" value="{{ old('club_nombre') }}"
                               class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-base focus:border-emerald-500 focus:outline-none">
                        @error('club_nombre') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-300" for="email">Email del que será administrador</label>
                        <input id="email" name="email" type="email" required maxlength="120" value="{{ old('email') }}"
                               class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-base focus:border-emerald-500 focus:outline-none">
                        @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-300" for="mensaje">Cuéntanos algo del club <span class="text-slate-500">(opcional)</span></label>
                        <textarea id="mensaje" name="mensaje" rows="3" maxlength="1000" placeholder="Secciones, número de socios, cómo lleváis hoy las plicas…"
                                  class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-base focus:border-emerald-500 focus:outline-none">{{ old('mensaje') }}</textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-emerald-600 py-3 text-base font-bold text-white hover:bg-emerald-500">
                        Solicitar acceso
                    </button>
                    <p class="text-center text-xs text-slate-500">
                        Solo usamos estos datos para darte de alta. <a href="{{ route('legal.privacidad') }}" class="underline hover:text-emerald-400">Privacidad</a>.
                        @if ($whatsapp)
                            <br>¿Prefieres hablar? <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="underline hover:text-emerald-400">Escríbenos por WhatsApp</a>.
                        @endif
                    </p>
                </form>
            @endif
        </div>
    </section>
@endsection
