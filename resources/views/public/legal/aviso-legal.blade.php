@extends('public.legal._layout', ['titulo' => 'Aviso legal'])

@section('legal')
    <p>En cumplimiento del artículo 10 de la Ley 34/2002, de 11 de julio, de Servicios de la Sociedad de la Información y de Comercio Electrónico (LSSI-CE), se informa de que el titular de este sitio web y del servicio Plica es:</p>

    @include('public.legal._titular')

    <h2>1. Objeto</h2>
    <p>Plica es un servicio en línea para clubes de pesca deportiva que permite gestionar sus mangas, pesajes, clasificaciones y rankings de temporada, y publicarlos para sus socios y para quien reciba un enlace. Este sitio web informa del servicio, recoge solicitudes de alta y da acceso a los paneles de los clubes.</p>

    <h2>2. Condiciones de uso</h2>
    <p>El acceso a este sitio es libre y gratuito. El uso de los paneles de administración y de socio queda reservado a los clubes dados de alta y a sus miembros, y se rige por las <a href="{{ route('legal.condiciones') }}">condiciones del servicio</a>. El usuario se compromete a usar el sitio conforme a la ley, a estas condiciones y a la buena fe, y a no introducir datos falsos ni de terceros sin su autorización.</p>

    <h2>3. Propiedad intelectual e industrial</h2>
    <p>El software, el diseño, los textos, el logotipo y la marca Plica son titularidad de {{ config('plica.legal.titular') }} o de sus licenciantes. Los nombres, escudos y datos de cada club son de su respectivo club. No se cede ningún derecho sobre el software más allá del uso del servicio en los términos contratados.</p>

    <h2>4. Responsabilidad</h2>
    <p>El titular trabaja para que el servicio esté disponible y sea correcto, pero no puede garantizar la ausencia de interrupciones o errores. Los resultados deportivos que publica cada club los introduce el propio club, que es responsable de su veracidad. El titular no responde de los contenidos de sitios de terceros a los que se enlace (por ejemplo, mapas de localización).</p>

    <h2>5. Protección de datos y cookies</h2>
    <p>El tratamiento de datos personales se describe en la <a href="{{ route('legal.privacidad') }}">política de privacidad</a>, y el uso de cookies en la <a href="{{ route('legal.cookies') }}">política de cookies</a>.</p>

    <h2>6. Legislación aplicable</h2>
    <p>Estas condiciones se rigen por la legislación española. Para cualquier controversia, y salvo que la ley disponga otra cosa, las partes se someten a los juzgados y tribunales del domicilio del titular.</p>
@endsection
