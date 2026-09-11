<?php

namespace Database\Seeders;

use App\Models\Captura;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use Illuminate\Database\Seeder;

/**
 * Bass Madrid, sección Orilla, temporada 2026: el primer club real de Plica,
 * cargado desde su hoja «CLASIFICACIÓN GENERAL BLACK BASS - AÑO 2026»
 * (septiembre de 2026). Sus reglas: cada manga suma los gramos pescados más
 * 500 puntos por asistir; gana quien más suma.
 *
 * Se puede ejecutar más de una vez sin romper nada: encuentra lo que ya existe
 * (club por slug, socios por nombre, mangas por fecha y sección) y solo pesa
 * las mangas que aún no tienen ninguna participación, así nunca pisa lo que el
 * admin haya tocado después.
 *
 *   php artisan db:seed --class=BassMadridSeeder --force
 */
class BassMadridSeeder extends Seeder
{
    public const SLUG = 'bass-madrid';

    /** Nº de la hoja del club => nombre, tal como está en la hoja (salvo mayúsculas y un «Isisdoro»). */
    public const SOCIOS = [
        1 => 'Fernando Arus',
        2 => 'Paco Casado',
        3 => 'Enedino Villaverde',
        4 => 'Tomás Tomás',
        5 => 'David Garrido',
        6 => 'José Durán',
        7 => 'Daniel Gómez',
        8 => 'Diego Magro',
        9 => 'Tony',
        10 => 'Tommy',
        11 => 'Domingo Antunez',
        12 => 'Marius Florea',
        13 => 'Valentin',
        14 => 'Marius Modi',
        15 => 'Fran Gomez',
        16 => 'Juan Francisco Trujillo',
        17 => 'Juan Juarez',
        18 => 'Victor Calvo',
        19 => 'Maria de la Hija',
        20 => 'Luis Enrique',
        21 => 'Isidoro Rodriguez',
        22 => 'Marcos Casado',
        23 => 'Oliver Gomez',
    ];

    /**
     * Mangas de la hoja. En «resultados», nº de socio => [piezas, gramos, pieza mayor en gramos, nota];
     * [0, 0, 0] es «fue y no pescó» (cuenta la asistencia). Quien no aparece, no fue.
     *
     * @return array<int, array{nombre: string, fecha: string, lugar: string, estado: string, resultados: array<int, array{0: int, 1: int, 2: int, 3?: string}>}>
     */
    public static function mangas(): array
    {
        return [
            [
                'nombre' => '1ª Manga', 'fecha' => '2026-03-01', 'lugar' => 'Almaraz', 'estado' => Manga::ESTADO_CELEBRADA,
                'resultados' => [
                    1 => [4, 2440, 1050], 16 => [0, 0, 0], 12 => [0, 0, 0], 7 => [1, 460, 460], 4 => [1, 630, 630],
                    10 => [1, 430, 430], 6 => [1, 540, 540], 9 => [1, 450, 450], 2 => [2, 1010, 530], 5 => [1, 610, 610],
                    8 => [1, 450, 450], 3 => [1, 920, 920], 11 => [0, 0, 0], 13 => [0, 0, 0], 14 => [0, 0, 0],
                ],
            ],
            [
                'nombre' => '2ª Manga', 'fecha' => '2026-03-15', 'lugar' => 'Sierra Brava', 'estado' => Manga::ESTADO_CELEBRADA,
                'resultados' => [
                    1 => [0, 0, 0], 16 => [1, 2040, 2040], 12 => [0, 0, 0], 7 => [0, 0, 0], 4 => [1, 1230, 1230],
                    10 => [1, 970, 970], 6 => [0, 0, 0], 9 => [0, 0, 0], 2 => [1, 1100, 1100], 8 => [0, 0, 0],
                    3 => [0, 0, 0], 23 => [0, 0, 0], 22 => [0, 0, 0],
                ],
            ],
            [
                'nombre' => '3ª Manga', 'fecha' => '2026-03-29', 'lugar' => 'Navalcán', 'estado' => Manga::ESTADO_CELEBRADA,
                'resultados' => [
                    1 => [1, 800, 800], 16 => [2, 1450, 730], 12 => [1, 1090, 1090], 7 => [2, 2630, 1780],
                    // La hoja apunta 2 piezas y 2300 g con una penalización de 1480 g (la pieza mayor):
                    // Plica no tiene penalizaciones, así que se carga lo que cuenta, 820 g de una pieza.
                    4 => [1, 820, 820, 'Hoja del club: 2 piezas, 2300 g, pieza mayor 1480 g, penalización de 1480 g. Cuentan 820 g.'],
                    10 => [1, 750, 750], 6 => [0, 0, 0], 9 => [1, 840, 840], 5 => [1, 510, 510], 8 => [0, 0, 0],
                    23 => [0, 0, 0], 13 => [0, 0, 0], 14 => [0, 0, 0], 17 => [0, 0, 0],
                ],
            ],
            [
                'nombre' => '4ª Manga', 'fecha' => '2026-06-07', 'lugar' => 'Cíjara', 'estado' => Manga::ESTADO_CELEBRADA,
                'resultados' => [
                    1 => [2, 1550, 840], 16 => [1, 520, 520], 12 => [2, 1510, 900], 7 => [0, 0, 0], 4 => [0, 0, 0],
                    10 => [1, 500, 500], 6 => [1, 1380, 1380], 9 => [0, 0, 0], 11 => [1, 750, 750], 23 => [0, 0, 0],
                ],
            ],
            [
                'nombre' => '5ª Manga', 'fecha' => '2026-06-21', 'lugar' => 'García de Sola', 'estado' => Manga::ESTADO_CELEBRADA,
                'resultados' => [1 => [0, 0, 0], 16 => [0, 0, 0], 12 => [0, 0, 0], 9 => [0, 0, 0]],
            ],
            // En la hoja no hay pesaje de esta: queda pendiente de gestionar en Plica.
            ['nombre' => '6ª Manga', 'fecha' => '2026-09-06', 'lugar' => 'Navalcán', 'estado' => Manga::ESTADO_PROGRAMADA, 'resultados' => []],
            ['nombre' => '7ª Manga', 'fecha' => '2026-09-20', 'lugar' => 'Sierra Brava', 'estado' => Manga::ESTADO_PROGRAMADA, 'resultados' => []],
        ];
    }

    public function run(): void
    {
        $club = Club::firstWhere('slug', self::SLUG);

        // Al desplegar se creó un club «Plica» vacío solo para tener una cuenta de admin:
        // ese club pasa a ser Bass Madrid, con su temporada activa y su admin.
        if ($club === null && ($plica = Club::where('slug', 'plica')->whereDoesntHave('socios')->first()) !== null) {
            $plica->update(['nombre' => 'Bass Madrid', 'slug' => self::SLUG, 'localidad' => 'Madrid']);
            $club = $plica;
        }

        $club ??= Club::create(['nombre' => 'Bass Madrid', 'slug' => self::SLUG, 'localidad' => 'Madrid']);

        $temporada = Temporada::firstOrCreate(['club_id' => $club->id, 'nombre' => 'Temporada 2026'], ['activa' => true]);

        $orilla = Seccion::firstOrCreate(['club_id' => $club->id, 'slug' => 'orilla'], [
            'nombre' => 'Orilla',
            'criterio' => Seccion::CRITERIO_PESO,
            'sistema_puntuacion' => Seccion::SISTEMA_ACUMULADO,
            'puntos_participacion' => 500,
            'descartes' => 0,
        ]);

        $socios = collect(self::SOCIOS)->map(fn (string $nombre) => Socio::firstOrCreate(['club_id' => $club->id, 'nombre' => $nombre]));

        foreach (self::mangas() as $def) {
            // Por fecha con whereDate: la columna guarda hora y firstOrCreate no la encontraría.
            $manga = Manga::query()
                ->where('temporada_id', $temporada->id)
                ->where('seccion_id', $orilla->id)
                ->whereDate('fecha', $def['fecha'])
                ->first() ?? Manga::create([
                    'temporada_id' => $temporada->id,
                    'seccion_id' => $orilla->id,
                    'fecha' => $def['fecha'],
                    'nombre' => $def['nombre'],
                    'lugar' => $def['lugar'],
                    'estado' => $def['estado'],
                ]);

            if ($def['resultados'] === [] || $manga->participacions()->exists()) {
                continue;
            }

            foreach ($def['resultados'] as $numero => $resultado) {
                [$piezas, $gramos, $mayor, $nota] = array_pad($resultado, 4, null);

                $participacion = Participacion::create([
                    'manga_id' => $manga->id,
                    'socio_id' => $socios[$numero]->id,
                    'seccion_id' => $orilla->id,
                    'plica' => true,
                    'pieza_mayor_gramos' => $mayor ?: null,
                ]);

                if ($piezas > 0) {
                    Captura::create([
                        'participacion_id' => $participacion->id,
                        'piezas' => $piezas,
                        'peso_gramos' => $gramos,
                        'nota' => $nota,
                    ]);
                }
            }
        }
    }
}
