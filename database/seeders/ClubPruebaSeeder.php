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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * «Club de Pruebas»: un club completo para que la gente toquetee Plica en
 * producción sin miedo. Tres secciones con reglas distintas, 16 socios, una
 * temporada con mangas ya pesadas (rankings hechos), dos mangas pasadas sin
 * pesar (salen como pendientes de gestionar) y tres próximas con ubicación y
 * socios que han dicho «Asistiré».
 *
 * Accesos: admin club@club.com / club1234 · socio socio@club.com / club1234.
 *
 * Reiniciable: cada ejecución deja el club como nuevo sin cambiar sus ids (club,
 * cuentas, secciones y socios se conservan; las mangas se regeneran), así los
 * enlaces y las sesiones de los testers siguen valiendo. Los datos son
 * deterministas; solo las fechas son relativas a hoy.
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
        // Reinicio «en el sitio»: el club, sus cuentas, secciones y socios conservan
        // sus ids (los enlaces y las sesiones de los testers siguen valiendo); solo
        // se tiran y regeneran las mangas con sus pesajes y asistencias. Lo que hayan
        // añadido los testers (socios, secciones, cuentas) se borra.
        $club = Club::updateOrCreate(['slug' => self::SLUG], [
            'nombre' => 'Club de Pruebas',
            'localidad' => 'Madrid',
            'descripcion' => 'Club de pruebas de Plica: toca lo que quieras. Se puede reiniciar en cualquier momento.',
            'email_contacto' => self::ADMIN_EMAIL,
            'perfil_publico' => true,
            'logo' => null,
        ]);

        Manga::whereHas('temporada', fn ($q) => $q->where('club_id', $club->id))->get()->each->delete(); // arrastra pesajes y asistencias

        $temporada = Temporada::updateOrCreate(['club_id' => $club->id, 'nombre' => 'Temporada '.today()->year], ['activa' => true]);
        Temporada::where('club_id', $club->id)->whereKeyNot($temporada->id)->delete();

        $secciones = [
            'orilla' => Seccion::updateOrCreate(['club_id' => $club->id, 'slug' => 'orilla'], ['nombre' => 'Orilla', 'criterio' => Seccion::CRITERIO_PESO, 'sistema_puntuacion' => Seccion::SISTEMA_ACUMULADO, 'puntos_participacion' => 500, 'puntos_no_asistencia' => 0, 'descartes' => 0, 'descartes_ausencias' => false, 'desempate' => Seccion::DESEMPATE_PIEZA_MAYOR]),
            // Por puestos, el sistema de federación: puesto = puntos, sin desempate (promedio), ausente = socios + 1.
            'embarcacion' => Seccion::updateOrCreate(['club_id' => $club->id, 'slug' => 'embarcacion'], ['nombre' => 'Embarcación', 'criterio' => Seccion::CRITERIO_PESO, 'sistema_puntuacion' => Seccion::SISTEMA_PUESTOS, 'puntos_participacion' => 0, 'puntos_no_asistencia' => 17, 'descartes' => 1, 'descartes_ausencias' => true, 'desempate' => Seccion::DESEMPATE_PROMEDIO]),
            'pato' => Seccion::updateOrCreate(['club_id' => $club->id, 'slug' => 'pato-lucio'], ['nombre' => 'Pato — Lucio', 'criterio' => Seccion::CRITERIO_MEDIDA, 'sistema_puntuacion' => Seccion::SISTEMA_ACUMULADO, 'puntos_participacion' => 0, 'puntos_no_asistencia' => 0, 'descartes' => 0, 'descartes_ausencias' => false, 'desempate' => Seccion::DESEMPATE_PIEZAS]),
        ];
        // La matriz de «suma lo pescado»: una sección por combinación de reglas, para cerrar el sistema.
        $matriz = $this->matrizAcumulado($club);
        Seccion::where('club_id', $club->id)->whereNotIn('id', collect($secciones)->pluck('id')->merge($matriz->pluck('id')))->delete();

        $socios = collect(self::SOCIOS)->map(fn (string $nombre, int $i) => Socio::updateOrCreate(['club_id' => $club->id, 'nombre' => $nombre], [
            'email' => $i % 3 === 0 ? str($nombre)->slug('.').'@ejemplo.es' : null,
            'telefono' => null,
            'activo' => $nombre !== 'Luis Barranco', // uno de baja, para verlo
        ]));
        Socio::where('club_id', $club->id)->whereNotIn('id', $socios->pluck('id'))->delete();

        $admin = $this->cuenta(self::ADMIN_EMAIL, 'Admin de pruebas', User::ROLE_ADMIN, $club);
        $userSocio = $this->cuenta(self::SOCIO_EMAIL, 'Mario López', User::ROLE_SOCIO, $club);
        $userSocio->forceFill(['guia_completada_at' => now()])->save();
        User::where('club_id', $club->id)->whereNotIn('id', [$admin->id, $userSocio->id])->delete();
        Socio::where('club_id', $club->id)->whereKeyNot($socios[0]->id)->update(['user_id' => null]);
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
                ['celebrada', -20], ['celebrada', -17], ['celebrada', -14], ['celebrada', -11], ['celebrada', -8], ['celebrada', -5],
                ['pendiente', 0], ['proxima', 2],
            ],
            'pato' => [
                ['celebrada', -11], ['celebrada', -5], ['proxima', 3],
            ],
        ];

        // Una manga de Embarcación con empates a propósito (dos a igual peso, cuatro a cero),
        // para que se vean los promedios del sistema por puestos: 1,5 y 5,5.
        $fijos = ['embarcacion' => [1 => [3 => [2, 1500, 900], 4 => [2, 1500, 900], 8 => [1, 800, 800], 9 => [0, 0, 0], 10 => [0, 0, 0], 11 => [0, 0, 0], 14 => [0, 0, 0]]]];

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

                if (isset($fijos[$clave][$i])) {
                    foreach ($fijos[$clave][$i] as $idx => [$piezas, $gramos, $mayor]) {
                        $participacion = Participacion::create(['manga_id' => $manga->id, 'socio_id' => $socios[$idx]->id, 'seccion_id' => $seccion->id, 'plica' => true, 'pieza_mayor_gramos' => $mayor ?: null]);
                        if ($piezas > 0) {
                            Captura::create(['participacion_id' => $participacion->id, 'piezas' => $piezas, 'peso_gramos' => $gramos]);
                        }
                    }

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

        $this->pesarMatriz($temporada, $matriz, $socios);
    }

    // ------------------------------------------------------------------
    //  Matriz de «suma lo pescado»: todas las variables, con datos que fuerzan
    //  empates (a valor, con distinta pieza mayor y distinto nº de piezas), ceros
    //  (fue y no pescó) y ausencias (una y dos mangas perdidas). Ocho reglas por
    //  criterio, 24 secciones; los resultados esperados están en MatrizAcumuladoTest,
    //  calculados aparte del motor.
    // ------------------------------------------------------------------

    /**
     * Las ocho combinaciones de reglas: [asistencia, desempate, descartes, también no pescadas, puntos por ausencia].
     * Desempate: A = pieza mayor, B = piezas (o peso en la de piezas), M = menos piezas (o peso en la de piezas).
     */
    public const MATRIZ_REGLAS = [
        1 => [0, 'A', 0, false, 0],
        2 => [500, 'B', 0, false, 0],
        3 => [0, 'compartido', 0, false, 0],
        4 => [500, 'A', 1, false, 0],
        5 => [0, 'B', 1, true, 0],
        6 => [500, 'compartido', 1, true, 0],
        7 => [500, 'M', 0, false, -200],
        8 => [0, 'A', 1, true, 100],
    ];

    /**
     * Resultados por criterio, manga y socio (A..F = los seis primeros socios).
     * Peso y piezas: [piezas, gramos, pieza mayor]. Medida: lista de peces en mm.
     * [] o [0,0,0] = fue y no pescó. Sin entrada = no fue.
     */
    public const MATRIZ_DATOS = [
        Seccion::CRITERIO_PESO => [
            ['A' => [2, 3000, 2000], 'B' => [3, 3000, 1500], 'C' => [1, 1000, 1000], 'D' => [0, 0, 0], 'E' => [1, 500, 500]],
            ['A' => [1, 2000, 2000], 'B' => [2, 2500, 1300], 'D' => [1, 1500, 1500], 'E' => [2, 1500, 800], 'F' => [1, 4000, 4000]],
            ['B' => [0, 0, 0], 'C' => [2, 2200, 1200], 'D' => [1, 700, 700], 'F' => [1, 1000, 1000]],
        ],
        Seccion::CRITERIO_PIEZAS => [
            ['A' => [3, 3400, 1500], 'B' => [3, 2400, 2000], 'C' => [1, 1000, 1000], 'D' => [0, 0, 0], 'E' => [2, 900, 500]],
            ['A' => [2, 2000, 1200], 'B' => [1, 2500, 2500], 'D' => [2, 1400, 900], 'E' => [2, 1400, 1000], 'F' => [4, 3000, 1200]],
            ['B' => [1, 300, 300], 'C' => [3, 2200, 1200], 'D' => [1, 700, 700], 'F' => [2, 1500, 900]],
        ],
        Seccion::CRITERIO_MEDIDA => [
            ['A' => [400, 400, 400], 'B' => [700, 500], 'C' => [500], 'D' => [], 'E' => [450]],
            ['A' => [800], 'B' => [650, 650], 'D' => [900], 'E' => [450, 450], 'F' => [1000, 500]],
            ['B' => [], 'C' => [700, 600], 'D' => [350], 'F' => [500]],
        ],
    ];

    public static function matrizSlug(string $criterio, int $regla): string
    {
        return "matriz-{$criterio}-{$regla}";
    }

    public static function matrizNombre(string $criterio, int $regla): string
    {
        [$asistencia, $desempate, $descartes, $ausencias, $ausencia] = self::MATRIZ_REGLAS[$regla];
        $criterioTexto = match ($criterio) {
            Seccion::CRITERIO_MEDIDA => 'Medida',
            Seccion::CRITERIO_PIEZAS => 'Piezas',
            default => 'Peso',
        };
        $desempateTexto = match ($desempate) {
            'A' => 'pieza mayor',
            'B' => $criterio === Seccion::CRITERIO_PIEZAS ? 'más peso' : 'más piezas',
            'M' => $criterio === Seccion::CRITERIO_PIEZAS ? 'más peso' : 'menos piezas',
            default => 'comparten',
        };
        $descartesTexto = $descartes === 0 ? 'sin descartes' : ($ausencias ? '1 descarte, también no pescadas' : '1 descarte, solo pescadas');
        $ausenciaTexto = $ausencia === 0 ? '' : ' · ausencia '.($ausencia > 0 ? '+' : '').$ausencia;

        return "{$criterioTexto} · ".($asistencia > 0 ? "asistencia {$asistencia}" : 'sin asistencia')." · {$desempateTexto} · {$descartesTexto}{$ausenciaTexto}";
    }

    public static function matrizDesempate(string $criterio, string $letra): string
    {
        return match ($letra) {
            'A' => Seccion::DESEMPATE_PIEZA_MAYOR,
            'B' => $criterio === Seccion::CRITERIO_PIEZAS ? Seccion::DESEMPATE_PESO : Seccion::DESEMPATE_PIEZAS,
            'M' => $criterio === Seccion::CRITERIO_PIEZAS ? Seccion::DESEMPATE_PESO : Seccion::DESEMPATE_MENOS_PIEZAS,
            default => Seccion::DESEMPATE_COMPARTIDO,
        };
    }

    /** @return Collection<int, Seccion> */
    private function matrizAcumulado(Club $club): Collection
    {
        $secciones = collect();

        foreach (array_keys(self::MATRIZ_DATOS) as $criterio) {
            foreach (self::MATRIZ_REGLAS as $regla => [$asistencia, $desempate, $descartes, $ausencias, $ausencia]) {
                $secciones->push(Seccion::updateOrCreate(['club_id' => $club->id, 'slug' => self::matrizSlug($criterio, $regla)], [
                    'nombre' => self::matrizNombre($criterio, $regla),
                    'criterio' => $criterio,
                    'sistema_puntuacion' => Seccion::SISTEMA_ACUMULADO,
                    'puntos_participacion' => $asistencia,
                    'puntos_no_asistencia' => $ausencia,
                    'descartes' => $descartes,
                    'descartes_ausencias' => $ausencias,
                    'desempate' => self::matrizDesempate($criterio, $desempate),
                ]));
            }
        }

        return $secciones;
    }

    private function pesarMatriz(Temporada $temporada, Collection $secciones, Collection $socios): void
    {
        $letras = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5];

        foreach ($secciones as $seccion) {
            $criterio = $seccion->criterio;

            foreach (self::MATRIZ_DATOS[$criterio] as $i => $resultados) {
                $manga = Manga::create([
                    'temporada_id' => $temporada->id,
                    'seccion_id' => $seccion->id,
                    'nombre' => ($i + 1).'ª Manga',
                    'fecha' => today()->subWeeks(9 - 3 * $i)->next('Sunday'),
                    'lugar' => 'Embalse de pruebas',
                    'estado' => Manga::ESTADO_CELEBRADA,
                ]);

                foreach ($resultados as $letra => $resultado) {
                    $participacion = Participacion::create(['manga_id' => $manga->id, 'socio_id' => $socios[$letras[$letra]]->id, 'seccion_id' => $seccion->id, 'plica' => true]);

                    if ($criterio === Seccion::CRITERIO_MEDIDA) {
                        foreach ($resultado as $mm) {
                            Captura::create(['participacion_id' => $participacion->id, 'piezas' => 1, 'peso_gramos' => 0, 'medida_mm' => $mm]);
                        }

                        continue;
                    }

                    [$piezas, $gramos, $mayor] = $resultado;

                    if ($piezas > 0) {
                        Captura::create(['participacion_id' => $participacion->id, 'piezas' => $piezas, 'peso_gramos' => $gramos]);
                        $participacion->update(['pieza_mayor_gramos' => $mayor]);
                    }
                }
            }
        }
    }

    /**
     * La cuenta con su contraseña conocida. Solo se vuelve a poner la contraseña si
     * cambió: un hash nuevo cerraría la sesión de quien esté dentro.
     */
    private function cuenta(string $email, string $nombre, string $rol, Club $club): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill(['name' => $nombre, 'club_id' => $club->id, 'role' => $rol]);

        if (! $user->exists || ! Hash::check(self::PASSWORD, $user->password)) {
            $user->password = self::PASSWORD;
            $user->password_cambiada_at = now();
        }

        $user->save();

        return $user;
    }
}
