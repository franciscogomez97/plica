@extends('public.legal._layout', ['titulo' => 'Política de cookies'])

@section('legal')
    <p>Plica usa <strong>solo cookies técnicas</strong>, las imprescindibles para que la web y los paneles funcionen. No hay cookies de análisis, de publicidad ni de terceros. Por eso no verás un aviso de cookies al entrar: las cookies estrictamente necesarias están exentas del consentimiento según el artículo 22.2 de la LSSI-CE y los criterios de la Agencia Española de Protección de Datos.</p>

    <h2>Qué cookies usamos</h2>
    <dl>
        <dt><code>plica_session</code></dt>
        <dd>Mantiene tu sesión mientras navegas y, si has entrado, te identifica en tu panel. Caduca al cerrar sesión o a los 30 días.</dd>
        <dt><code>XSRF-TOKEN</code></dt>
        <dd>Protege los formularios contra envíos falsificados desde otras webs. Dura lo que la sesión.</dd>
        <dt><code>remember_web_…</code></dt>
        <dd>Solo si marcas «Recordarme» al entrar: evita que tengas que volver a escribir la contraseña en ese dispositivo. Dura hasta que cierras sesión.</dd>
    </dl>
    <p>Todas son cookies propias, de primera parte, y no contienen datos personales legibles: su contenido está cifrado.</p>

    <h2>Lo que no hay</h2>
    <ul>
        <li>Ni Google Analytics ni ninguna otra herramienta de medición.</li>
        <li>Ni píxeles ni cookies de redes sociales o de publicidad.</li>
        <li>Ni contenido incrustado de terceros que instale cookies. Los enlaces a mapas se abren fuera de Plica.</li>
    </ul>

    <h2>Cómo borrarlas</h2>
    <p>Puedes borrar o bloquear las cookies desde la configuración de tu navegador. Ten en cuenta que, sin la cookie de sesión, no podrás entrar en tu panel. Las páginas públicas (rankings y clasificaciones compartidas) funcionan igualmente.</p>

    <h2>Si algún día cambia</h2>
    <p>Si incorporamos cookies que requieran consentimiento, esta página lo indicará y aparecerá el aviso correspondiente antes de instalarlas.</p>
@endsection
