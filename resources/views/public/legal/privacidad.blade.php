@extends('public.legal._layout', ['titulo' => 'Política de privacidad'])

@section('legal')
    <p>Esta política explica qué datos personales trata Plica, para qué, con qué base legal y qué derechos tienes, conforme al Reglamento (UE) 2016/679 (RGPD) y a la Ley Orgánica 3/2018 (LOPDGDD). Está escrita para que se entienda; si algo no queda claro, escríbenos.</p>

    <h2>1. Quién es el responsable</h2>
    <p>Depende de qué datos hablemos:</p>
    <ul>
        <li><strong>Datos de la web y de las solicitudes de alta</strong> (formulario «Solicita acceso», correos que nos envíes): el responsable es el titular del servicio.</li>
        <li><strong>Datos de los socios de un club</strong> (nombres, emails, resultados de pesca): el responsable es <strong>el club</strong>, que decide qué datos introduce y qué publica. Plica actúa como <strong>encargado del tratamiento</strong> por cuenta del club, según el contrato de encargo que forma parte de las <a href="{{ route('legal.condiciones') }}">condiciones del servicio</a>. Para ejercer tus derechos sobre estos datos, dirígete primero a tu club; nosotros le ayudaremos a atenderte.</li>
    </ul>

    @include('public.legal._titular')

    <h2>2. Qué datos tratamos</h2>
    <ul>
        <li><strong>Solicitudes de alta:</strong> nombre del club, email de la persona que solicita y el mensaje que escriba.</li>
        <li><strong>Cuentas de acceso</strong> (administradores y socios con cuenta): nombre, email, contraseña cifrada, club al que pertenecen y fecha de los accesos.</li>
        <li><strong>Datos deportivos de los socios</strong> introducidos por el club: nombre, participación en cada manga, capturas (piezas, peso, medida, pieza mayor), confirmaciones de asistencia y, si el club lo indica, email.</li>
        <li><strong>Datos técnicos:</strong> dirección IP, navegador y registros de acceso, necesarios para prestar el servicio y protegerlo.</li>
    </ul>
    <p>No tratamos datos de categorías especiales, ni datos bancarios (los pagos de los clubes se hacen por transferencia, fuera de la plataforma), ni datos de menores salvo que el club los introduzca bajo su responsabilidad.</p>

    <h2>3. Para qué y con qué base legal</h2>
    <ul>
        <li><strong>Atender tu solicitud de alta y contactarte</strong>: consentimiento al enviar el formulario (art. 6.1.a RGPD).</li>
        <li><strong>Prestar el servicio al club</strong> (cuentas, paneles, cálculo y publicación de clasificaciones): ejecución del contrato con el club (art. 6.1.b) y, respecto a los socios, el encargo de tratamiento que el club nos confía.</li>
        <li><strong>Seguridad, prevención de abusos y copias de seguridad</strong>: interés legítimo en mantener el servicio (art. 6.1.f).</li>
        <li><strong>Obligaciones legales</strong> (facturación, requerimientos): cumplimiento de una obligación legal (art. 6.1.c).</li>
    </ul>
    <p>No usamos tus datos para publicidad ni los cedemos a terceros con fines comerciales. No hay perfilado ni decisiones automatizadas con efectos jurídicos.</p>

    <h2>4. Publicación de resultados</h2>
    <p>Las clasificaciones de las mangas y los rankings de temporada muestran el <strong>nombre del pescador y sus resultados</strong>. Cada club decide compartirlos mediante enlaces públicos (por ejemplo, en su grupo de WhatsApp); cualquiera que tenga el enlace puede verlos. Es la práctica habitual de la competición deportiva y el club, como responsable, informa a sus socios. Si no quieres aparecer, díselo a tu club: puede darte de baja de las competiciones.</p>

    <h2>5. Cuánto tiempo conservamos los datos</h2>
    <ul>
        <li>Solicitudes de alta: hasta 12 meses si no dan lugar a un alta.</li>
        <li>Datos del club y de sus socios: mientras el club use el servicio. Si el club no renueva, los datos pasan a solo lectura y no se borran; el club puede pedir su supresión o su exportación en cualquier momento.</li>
        <li>Registros técnicos: un máximo de 12 meses.</li>
        <li>Datos de facturación: los plazos que exige la legislación fiscal.</li>
    </ul>

    <h2>6. Quién puede acceder a los datos</h2>
    <p>Solo el titular y los proveedores estrictamente necesarios para prestar el servicio, que actúan como encargados o subencargados con las garantías del artículo 28 RGPD: el alojamiento de la aplicación y de las copias de seguridad, en centros de datos de la <strong>Unión Europea</strong>, y, si se activa, el envío de correos transaccionales. No hay transferencias internacionales fuera del Espacio Económico Europeo.</p>

    <h2>7. Seguridad</h2>
    <p>Conexiones cifradas (HTTPS), contraseñas almacenadas con cifrado irreversible, aislamiento de los datos de cada club, copias de seguridad diarias y accesos restringidos. Guardamos lo mínimo: en Plica no hay teléfonos, direcciones ni documentos de identidad de los socios.</p>

    <h2>8. Tus derechos</h2>
    <p>Puedes ejercer los derechos de acceso, rectificación, supresión, oposición, limitación del tratamiento y portabilidad escribiendo a <a href="mailto:{{ config('plica.legal.email') }}">{{ config('plica.legal.email') }}</a> (o a tu club, si se trata de tus datos como socio). Te responderemos en el plazo de un mes. Si crees que no hemos atendido tus derechos, puedes reclamar ante la Agencia Española de Protección de Datos (<a href="https://www.aepd.es" rel="noopener" target="_blank">www.aepd.es</a>).</p>

    <h2>9. Cambios en esta política</h2>
    <p>Si cambiamos algo relevante, lo indicaremos aquí con la fecha de actualización y, si afecta a los clubes, se lo comunicaremos a sus administradores.</p>
@endsection
