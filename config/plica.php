<?php

return [
    // Email del dueño de la plataforma: ve las solicitudes de acceso de la landing.
    'superadmin_email' => env('PLICA_SUPERADMIN_EMAIL'),

    // Dónde avisar cuando llega una solicitud nueva (por defecto, al superadmin).
    'notificaciones_email' => env('PLICA_NOTIFICACIONES_EMAIL', env('PLICA_SUPERADMIN_EMAIL')),
];
