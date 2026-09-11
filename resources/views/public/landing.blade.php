@extends('layouts.public')

@section('title', 'Plica — la app de tu club de pesca')

@section('content')
    @php
        $demo = \App\Models\Club::where('slug', 'cd-pesca-piloto')->where('perfil_publico', true)->first();
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
        <p class="mt-4 text-sm text-slate-500">Gratis hasta enero. Te lo montamos nosotros en una videollamada de media hora.</p>
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

    {{-- Lo que hace --}}
    <section class="py-10">
        <h2 class="text-center text-2xl font-extrabold tracking-tight sm:text-3xl">Hecho para clubes de pesca, no para «clubes deportivos»</h2>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h3 class="font-bold text-emerald-400">Secciones con sus reglas</h3>
                <p class="mt-2 text-sm text-slate-400">Orilla, embarcación, pato, black bass, carpfishing… Cada sección compite por peso, medida o piezas, con sus descartes, sus puntos por participar y su desempate. Sin ranking general inventado.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h3 class="font-bold text-emerald-400">Pieza mayor y desempates</h3>
                <p class="mt-2 text-sm text-slate-400">Siempre hay premio a la pieza mayor: Plica la calcula por manga y por temporada. Y si dos empatan, gana lo que diga vuestro reglamento, nunca el azar.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h3 class="font-bold text-emerald-400">Manga a manga</h3>
                <p class="mt-2 text-sm text-slate-400">Un cuadro tipo hoja de cálculo: pescadores en filas, mangas en columnas, quién ganó cada una y quién va ganando. Como el Excel del secretario, pero solo.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h3 class="font-bold text-emerald-400">Socios en cinco minutos</h3>
                <p class="mt-2 text-sm text-slate-400">Pegas la lista de nombres tal cual la tengas y les mandas su enlace de acceso por WhatsApp. Sin registros, sin contraseñas que olvidar.</p>
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

    {{-- Precio --}}
    <section class="py-10">
        <div class="mx-auto max-w-2xl rounded-3xl border border-emerald-900/60 bg-emerald-950/40 p-8 text-center">
            <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Un precio que se aprueba en asamblea</h2>
            <p class="mt-6 text-5xl font-extrabold tracking-tight">150 €<span class="text-xl font-semibold text-slate-400"> / temporada</span></p>
            <p class="mt-2 text-sm text-slate-400">IVA incluido. Una factura al año, en enero, cuando el club cobra las cuotas.</p>
            <ul class="mx-auto mt-6 max-w-md space-y-2 text-left text-sm text-slate-300">
                <li class="flex gap-2"><span class="text-emerald-400">✓</span> Gratis hasta el 1 de enero para los clubes que entren este otoño.</li>
                <li class="flex gap-2"><span class="text-emerald-400">✓</span> Los diez primeros clubes: 99 € por temporada, para siempre.</li>
                <li class="flex gap-2"><span class="text-emerald-400">✓</span> Alta acompañada: media hora de videollamada y te lo dejamos montado con tus socios.</li>
                <li class="flex gap-2"><span class="text-emerald-400">✓</span> Sin permanencia. Si el club no renueva, los datos se conservan en solo lectura; nunca se borran.</li>
            </ul>
            <p class="mt-6 text-sm text-slate-500">Menos que los trofeos de una manga. Menos de 2 € por socio y año en un club de 80.</p>
        </div>
    </section>

    {{-- Preguntas --}}
    <section class="py-10">
        <h2 class="text-center text-2xl font-extrabold tracking-tight sm:text-3xl">Lo que preguntan los presidentes</h2>
        <div class="mx-auto mt-8 max-w-3xl divide-y divide-slate-800 rounded-2xl border border-slate-800 bg-slate-900">
            <details class="group p-5">
                <summary class="cursor-pointer list-none font-semibold">¿Mi reglamento es distinto?
                    <span class="float-right text-slate-500 group-open:rotate-45">+</span></summary>
                <p class="mt-2 text-sm text-slate-400">Cada sección se configura con sus reglas: por peso, medida o piezas; descartes; puntos por participar; qué decide un empate. Y la app te lo explica en una frase para que compruebes que es lo vuestro. Si falta algo que un club real necesite, lo añadimos.</p>
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
                <p class="mt-2 text-sm text-slate-400">Normalmente el mismo día. Nos mandas el nombre del club y el email del que será administrador, y te llega el acceso. Luego, media hora de videollamada para meter secciones, socios y la primera manga juntos.</p>
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
                    </p>
                </form>
            @endif
        </div>
    </section>
@endsection
