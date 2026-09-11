<?php

namespace Database\Seeders;

use App\Models\Captura;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * «Club de Pruebas»: un club completo para que la gente toquetee Plica en
 * producción sin miedo. Tres secciones con reglas distintas, 16 socios, una
 * temporada con mangas ya pesadas (rankings hechos), dos mangas pasadas sin
 * pesar (salen como pendientes de gestionar) y tres próximas con ubicación y
 * socios que han dicho «Asistiré».
 *
 * Accesos: admin club@club.com / club1234 · socio socio@club.com / club1234.
 *
 * Reiniciable: cada ejecución borra el club de pruebas entero y lo vuelve a
 * crear igual (los datos son deterministas; solo las fechas son relativas a hoy).
 *
 *   php artisan db:seed --class=ClubPruebaSeeder --force
 */
class ClubPruebaSeeder extends Seeder
{
    public const SLUG = 'club-de-pruebas';

    public const ADMIN_EMAIL = 'club@club.com';

    public const SOCIO_EMAIL = 'socio@club.com';

    public const PASSWORD = 'club1234';

    public const SOCIOS = [
        'Mario López', 'Paco Jiménez', 'Andrés Molina', 'Sergio del Río',
        'Rubén Castaño', 'Iván Perea', 'Toni Salgado', 'Jorge Vidal',
        'Dani Cuesta', 'Alberto Rey', 'Chema Ortiz', 'Luis Barranco',
        'Marta Ruiz', 'Óscar Peña', 'Nacho Ferrer', 'Javi Soler',
    ];

    public function run(): void
    {
        $this->borrar();

        $club = Club::create([
            'nombre' => 'Club de Pruebas',
            'slug' => self::SLUG,
            'localidad' => 'Madrid',
            'descripcion' => 'Club de pruebas de Plica: toca lo que quieras. Se puede reiniciar en cualquier momento.',
            'email_contacto' => self::ADMIN_EMAIL,
            'perfil_publico' => true,
        ]);

        $temporada = Temporada::create(['club_id' => $club->id, 'nombre' => 'Temporada '.today()->year, 'activa' => true]);

        $secciones = [
            'orilla' => Seccion::create(['club_id' => $club->id, 'nombre' => 'Orilla', 'criterio' => Seccion::CRITERIO_PESO, 'puntos_participacion' => 500, 'desempate' => Seccion::DESEMPATE_PIEZA_MAYOR]),
            // Por puestos, el sistema de federación: puesto = puntos, empates promediados, ausente = socios + 1.
            'embarcacion' => Seccion::create(['club_id' => $club->id, 'nombre' => 'Embarcación', 'criterio' => Seccion::CRITERIO_PESO, 'sistema_puntuacion' => Seccion::SISTEMA_PUESTOS, 'puestos_empate' => Seccion::EMPATE_PROMEDIO, 'puntos_no_asistencia' => 17, 'descartes' => 1]),
            'pato' => Seccion::create(['club_id' => $club->id, 'nombre' => 'Pato — Lucio', 'criterio' => Seccion::CRITERIO_MEDIDA]),
        ];

        $socios = collect(self::SOCIOS)->map(fn (string $nombre, int $i) => Socio::create([
            'club_id' => $club->id,
            'nombre' => $nombre,
            'email' => $i % 3 === 0 ? str($nombre)->slug('.').'@ejemplo.es' : null,
            'activo' => $nombre !== 'Luis Barranco', // uno de baja, para verlo
        ]));

        User::create(['name' => 'Admin de pruebas', 'email' => self::ADMIN_EMAIL, 'password' => self::PASSWORD, 'club_id' => $club->id, 'role' => User::ROLE_ADMIN])
            ->forceFill(['password_cambiada_at' => now()])->save();

        $userSocio = User::create(['name' => 'Mario López', 'email' => self::SOCIO_EMAIL, 'password' => self::PASSWORD, 'club_id' => $club->id, 'role' => User::ROLE_SOCIO]);
        $userSocio->forceFill(['password_cambiada_at' => now(), 'guia_completada_at' => now()])->save();
        $socios[0]->update(['user_id' => $userSocio->id, 'email' => self::SOCIO_EMAIL]);

        // Quién pesca en cada sección (algunos, en dos).
        $plantilla = [
            'orilla' => [0, 1, 2, 3, 4, 5, 6, 7, 12, 13],
            'embarcacion' => [3, 4, 8, 9, 10, 11, 14],
            'pato' => [5, 6, 7, 12, 15],
        ];

        $lugares = ['Embalse de San Juan', 'Pantano de Buendía', 'Embalse de Entrepeñas', 'Embalse de Valdecañas', 'Embalse de Alcántara', 'Embalse de Orellana'];

        // Calendario por sección: celebradas (con pesaje), pasadas sin pesar (pendientes) y próximas.
        $calendario = [
            'orilla' => [
                ['celebrada', -16], ['celebrada', -12], ['celebrada', -8], ['celebrada', -4],
                ['pendiente', -1], ['proxima', 1],
            ],
            'embarcacion' => [
                ['celebrada', -14], ['celebrada', -10], ['celebrada', -6],
                ['pendiente', 0], ['proxima', 2],
            ],
            'pato' => [
                ['celebrada', -11], ['celebrada', -5], ['proxima', 3],
            ],
        ];

        mt_srand(20260911); // mismos resultados en cada reinicio

        foreach ($calendario as $clave => $mangas) {
            $seccion = $secciones[$clave];

            foreach ($mangas as $i => [$tipo, $semanas]) {
                $fecha = $tipo === 'pendiente'
                    ? today()->subDays($clave === 'orilla' ? 6 : 2)
                    : today()->addWeeks($semanas)->next('Sunday');

                $manga = Manga::create([
                    'temporada_id' => $temporada->id,
                    'seccion_id' => $seccion->id,
                    'nombre' => ($i + 1).'ª Manga',
                    'fecha' => $fecha,
                    'lugar' => $lugares[($i + array_search($clave, array_keys($calendario), true)) % count($lugares)],
                    'ubicacion_url' => $tipo === 'proxima' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($lugares[($i + array_search($clave, array_keys($calendario), true)) % count($lugares)]) : null,
                    'estado' => $tipo === 'celebrada' ? Manga::ESTADO_CELEBRADA : Manga::ESTADO_PROGRAMADA,
                    'notas' => $tipo === 'pendiente' ? 'Ya se ha pescado: falta marcar la asistencia y meter las plicas.' : null,
                ]);

                if ($tipo === 'proxima') {
                    // Unos cuantos ya han dicho «Asistiré».
                    foreach (array_slice($plantilla[$clave], 0, 4) as $idx) {
                        $manga->alternarConfirmacion($socios[$idx]);
                    }

                    continue;
                }

                if ($tipo !== 'celebrada') {
                    continue;
                }

                foreach ($plantilla[$clave] as $idx) {
                    if (mt_rand(1, 100) > 80) {
                        continue; // no fue
                    }

                    $participacion = Participacion::create([
                        'manga_id' => $manga->id,
                        'socio_id' => $socios[$idx]->id,
                        'seccion_id' => $seccion->id,
                        'plica' => true,
                    ]);

                    $piezas = [0, 0, 1, 1, 1, 2, 2, 3, 4][mt_rand(0, 8)];

                    if ($piezas === 0) {
                        continue; // fue y no pescó: cuenta la asistencia
                    }

                    if ($seccion->criterio === Seccion::CRITERIO_MEDIDA) {
                        // Un pez por captura, en milímetros (lucios de 45 a 95 cm).
                        for ($p = 0; $p < $piezas; $p++) {
                            Captura::create(['participacion_id' => $participacion->id, 'piezas' => 1, 'peso_gramos' => 0, 'medida_mm' => mt_rand(90, 190) * 5]);
                        }

                        continue;
                    }

                    $pesos = [];
                    for ($p = 0; $p < $piezas; $p++) {
                        $pesos[] = mt_rand(30, 250) * 10; // 300 g a 2,5 kg
                    }

                    Captura::create(['participacion_id' => $participacion->id, 'piezas' => $piezas, 'peso_gramos' => array_sum($pesos)]);
                    $participacion->update(['pieza_mayor_gramos' => max($pesos)]);
                }
            }
        }
    }

    /** Fuera todo lo del club de pruebas, en el orden que permiten las claves foráneas. */
    private function borrar(): void
    {
        $club = Club::where('slug', self::SLUG)->first();

        if ($club === null) {
            return;
        }

        Manga::whereHas('temporada', fn ($q) => $q->where('club_id', $club->id))->get()->each->delete(); // arrastra participaciones, capturas y asistencias
        User::where('club_id', $club->id)->delete();
        $club->delete(); // arrastra secciones, temporadas y socios
    }
}
