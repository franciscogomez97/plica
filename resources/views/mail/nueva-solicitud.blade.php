<p>Ha llegado una solicitud nueva desde la landing:</p>

<ul>
    <li><strong>Club:</strong> {{ $solicitud->club_nombre }}</li>
    <li><strong>Email del futuro admin:</strong> {{ $solicitud->email }}</li>
    <li><strong>Mensaje:</strong> {{ $solicitud->mensaje ?: '—' }}</li>
</ul>

<p>Para montárselo:</p>
<pre>php artisan plica:club "{{ $solicitud->club_nombre }}" {{ $solicitud->email }}</pre>

<p>Y responde a su email con el acceso. Prometimos «normalmente el mismo día» 😉</p>
