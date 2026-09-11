@php $legal = config('plica.legal'); @endphp
<div class="titular">
    <dl>
        <dt>Titular</dt><dd>{{ $legal['titular'] }}</dd>
        <dt>NIF</dt><dd>{{ $legal['nif'] }}</dd>
        <dt>Domicilio</dt><dd>{{ $legal['direccion'] }}</dd>
        <dt>Contacto</dt><dd><a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a></dd>
    </dl>
</div>
