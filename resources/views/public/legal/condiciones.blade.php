@extends('public.legal._layout', ['titulo' => 'Condiciones del servicio'])

@section('legal')
    <p>Estas condiciones regulan el uso de Plica por parte de los clubes de pesca deportiva («el club») y sus miembros. Al solicitar el alta y usar el panel de administración, el club las acepta. Incluyen, como anexo, el contrato de encargo del tratamiento de datos personales que exige el artículo 28 del RGPD.</p>

    @include('public.legal._titular')

    <h2>1. El servicio</h2>
    <p>Plica es un servicio en línea (software como servicio) para gestionar mangas, pesajes, clasificaciones y rankings de temporada de un club de pesca, y publicarlos a sus socios y a quien reciba un enlace. Incluye el panel de administración del club, el panel de socio, las páginas públicas del club y el alta acompañada: el titular crea el club, sus secciones, sus socios y su calendario junto con el administrador.</p>

    <h2>2. Alta y cuentas</h2>
    <ul>
        <li>El alta se solicita desde la web y la realiza el titular. El club designa al menos un administrador, responsable de los datos que introduce y de las cuentas de acceso que reparte a sus socios.</li>
        <li>Las cuentas son personales. Los enlaces de acceso son de un solo uso y el administrador debe enviarlos únicamente a la persona a la que corresponden.</li>
        <li>El club garantiza que tiene derecho a introducir los datos de sus socios y que les ha informado de que sus resultados deportivos se publican en la plataforma.</li>
    </ul>

    <h2>3. Precio y pago</h2>
    <ul>
        <li>El precio es de <strong>150 € por temporada, IVA incluido</strong>, por club, sin límite de socios ni de mangas. Los clubes que entren durante el otoño no pagan hasta el 1 de enero siguiente. Los diez primeros clubes («clubes fundadores») pagan 99 € por temporada mientras mantengan el servicio.</li>
        <li>La temporada va del 1 de enero al 31 de diciembre. En noviembre el club recibe el resumen de su temporada y la factura de la siguiente, con vencimiento el 31 de enero. El pago se hace por transferencia bancaria.</li>
        <li>Si el 1 de marzo no se ha recibido el pago, el club pasa a <strong>solo lectura</strong>: los socios siguen viendo sus rankings, pero no se pueden crear mangas ni pesajes hasta regularizarlo. <strong>Nunca se borran datos por impago.</strong></li>
        <li>El titular puede actualizar el precio para la temporada siguiente avisando antes del 1 de noviembre. Los clubes fundadores conservan su precio.</li>
    </ul>

    <h2>4. Duración y baja</h2>
    <p>El servicio se presta por temporadas, sin permanencia. El club puede darse de baja en cualquier momento escribiendo al titular; no se devuelven importes de la temporada en curso. Al causar baja, el club puede pedir una <strong>exportación de todos sus datos</strong> (socios, mangas y capturas) y su supresión definitiva.</p>

    <h2>5. Obligaciones del titular</h2>
    <ul>
        <li>Mantener el servicio disponible con diligencia, con copias de seguridad diarias y alojamiento en la Unión Europea. Las interrupciones por mantenimiento se harán, en lo posible, fuera de los fines de semana de competición.</li>
        <li>Corregir los errores del cálculo de clasificaciones que se le comuniquen. Los hechos (participaciones y capturas) no se modifican nunca desde el titular: solo el club los edita.</li>
        <li>Si el titular decidiera cerrar el servicio, avisará con <strong>al menos un año</strong> de antelación y entregará a cada club una exportación completa de sus datos.</li>
    </ul>

    <h2>6. Obligaciones del club</h2>
    <ul>
        <li>Usar el servicio para la gestión deportiva del club, con datos veraces y sin introducir contenidos ilícitos o de terceros sin permiso.</li>
        <li>Custodiar las credenciales de administrador y comunicar cualquier acceso no autorizado.</li>
        <li>Atender los derechos de sus socios sobre sus datos, con la ayuda del titular cuando la necesite.</li>
    </ul>

    <h2>7. Responsabilidad</h2>
    <p>El titular responde de la prestación del servicio con la diligencia debida, pero no de las decisiones deportivas que el club tome a partir de las clasificaciones, ni de los datos que el club introduzca, ni de daños indirectos. En cualquier caso, la responsabilidad total del titular frente a un club se limita al importe pagado por ese club en la temporada en curso.</p>

    <h2>8. Modificaciones y ley aplicable</h2>
    <p>El titular puede actualizar estas condiciones; los cambios relevantes se comunican a los administradores con antelación y se aplican a partir de la temporada siguiente. Estas condiciones se rigen por la legislación española.</p>

    <h2>Anexo: contrato de encargo del tratamiento (art. 28 RGPD)</h2>
    <p>Este anexo forma parte de las condiciones y se entiende aceptado por el club al usar el servicio.</p>

    <h3>1. Partes y objeto</h3>
    <p>El <strong>club</strong> es el responsable del tratamiento de los datos personales de sus socios. El <strong>titular de Plica</strong> es el encargado del tratamiento, y trata esos datos exclusivamente para prestar el servicio descrito en estas condiciones, siguiendo las instrucciones del club.</p>

    <h3>2. Duración</h3>
    <p>La del servicio. Al finalizar, el encargado devolverá los datos al club en un formato de uso común (exportación) y los suprimirá, salvo los que deba conservar por obligación legal, que quedarán bloqueados.</p>

    <h3>3. Naturaleza, finalidad y datos</h3>
    <p>Almacenamiento, cálculo y publicación de clasificaciones y rankings, comunicación entre club y socios (convocatorias, confirmaciones) y gestión de cuentas de acceso. Datos: identificativos (nombre, email), de pertenencia al club y resultados deportivos. Categorías de interesados: socios y administradores del club.</p>

    <h3>4. Obligaciones del encargado</h3>
    <ul>
        <li>Tratar los datos solo según las instrucciones del club y para las finalidades del servicio; nunca para fines propios.</li>
        <li>Garantizar la confidencialidad de las personas que accedan a los datos.</li>
        <li>Aplicar medidas de seguridad adecuadas: cifrado en tránsito, contraseñas cifradas, aislamiento por club, copias de seguridad, control de accesos.</li>
        <li>No comunicar los datos a terceros salvo a los subencargados necesarios para el servicio (alojamiento y copias en la Unión Europea; en su caso, envío de correo), informando al club de cualquier cambio y trasladándoles las mismas obligaciones.</li>
        <li>Ayudar al club a atender los derechos de los interesados y a cumplir sus obligaciones de seguridad y de notificación de brechas, comunicándole cualquier violación de seguridad sin dilación indebida.</li>
        <li>Poner a disposición del club la información necesaria para demostrar el cumplimiento de estas obligaciones.</li>
        <li>Suprimir o devolver los datos al finalizar el servicio, conforme al punto 2.</li>
    </ul>

    <h3>5. Obligaciones del club (responsable)</h3>
    <ul>
        <li>Facilitar al encargado solo los datos necesarios y contar con base legal para tratarlos.</li>
        <li>Informar a sus socios del tratamiento y de la publicación de resultados, y atender sus derechos.</li>
        <li>Velar, antes y durante el tratamiento, por el cumplimiento del RGPD por parte del encargado.</li>
    </ul>
@endsection
