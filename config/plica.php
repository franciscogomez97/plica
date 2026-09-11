<?php

return [
    // Email del dueño de la plataforma: ve las solicitudes de acceso de la landing.
    'superadmin_email' => env('PLICA_SUPERADMIN_EMAIL'),

    // Dónde avisar cuando llega una solicitud nueva (por defecto, al superadmin).
    'notificaciones_email' => env('PLICA_NOTIFICACIONES_EMAIL', env('PLICA_SUPERADMIN_EMAIL')),

    // WhatsApp de atención a clubes, en formato internacional sin «+» (p. ej. 34600111222).
    // Si está, la landing enseña «Escríbenos por WhatsApp»; si no, solo el formulario.
    'whatsapp' => env('PLICA_WHATSAPP'),

    // Plan comercial. Las fechas salen de aquí para que landing y condiciones
    // digan lo mismo y no caduquen sin que nadie se dé cuenta.
    'plan' => [
        'precio' => 150,           // € por temporada, IVA incluido
        'fundadores' => 99,        // € por temporada para los 10 primeros clubes
        'gratis_hasta' => '2027-01-01', // los clubes que entren antes no pagan hasta esta fecha
    ],

    // Titular del servicio para el aviso legal, la privacidad y las condiciones.
    // RELLENAR EN PRODUCCIÓN (.env). Mientras, salen los valores entre corchetes.
    'legal' => [
        'titular' => env('PLICA_TITULAR', '[Nombre o razón social del titular]'),
        'nif' => env('PLICA_NIF', '[NIF]'),
        'direccion' => env('PLICA_DIRECCION', '[Dirección postal]'),
        'email' => env('PLICA_EMAIL_LEGAL', env('PLICA_SUPERADMIN_EMAIL', '[email de contacto]')),
        'actualizado' => '11 de septiembre de 2026',
    ],
];
