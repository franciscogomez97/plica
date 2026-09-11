@php $legal = config('plica.legal'); @endphp
<div class="titular">
    <dl>
        <dt>Titular</dt><dd>{{ $legal['titular'] }}</dd>
        @if (filled($legal['nif']))
            <dt>NIF</dt><dd>{{ $legal['nif'] }}</dd>
        @endif
        @if (filled($legal['direccion']))
            <dt>Domicilio</dt><dd>{{ $legal['direccion'] }}</dd>
        @endif
        <dt>Contacto</dt><dd><a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a></dd>
    </dl>
</div>
