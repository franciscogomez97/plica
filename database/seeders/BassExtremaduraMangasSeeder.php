<?php

namespace Database\Seeders;

use App\Models\Captura;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Socio;
use Illuminate\Database\Seeder;

/**
 * Las dos mangas de Orilla 2026 que Bass Extremadura ha celebrado hasta el
 * 25 de junio de 2026, tal cual sus hojas (I Manga Orellana 15/3/26 y la II
 * Manga de la imagen de WhatsApp; la «General Provisional Orilla 2026» del
 * 25 de junio solo tiene esas dos columnas rellenas de cinco). No se inventa
 * ninguna otra: las columnas III, IV y V están vacías en su hoja.
 *
 * La II Manga no trae fecha ni embalse en la imagen: se carga con la fecha
 * de la hoja general (25/6/2026) y lugar «por confirmar», para que el admin
 * lo corrija en «Datos de la manga».
 *
 * Idempotente: solo carga una manga si la sección no tiene ya una con esa fecha.
 *
 *   php artisan db:seed --class=BassExtremaduraMangasSeeder --force
 */
class BassExtremaduraMangasSeeder extends Seeder
{
    /**
     * Por manga: socio => [piezas, gramos, pieza mayor]. [0, 0, 0] = fue y no pescó (bolo).
     * Quien no aparece, no fue (48).
     *
     * @return array<int, array{nombre: string, fecha: string, lugar: string, notas: ?string, resultados: array<string, array{int, int, int}>}>
     */
    public static function mangas(): array
    {
        return [
            [
                'nombre' => 'I Manga', 'fecha' => '2026-03-15', 'lugar' => 'Orellana', 'notas' => null,
                'resultados' => [
                    'Adrián Fernandez' => [2, 5660, 2975], 'Miguel García' => [1, 1395, 1395], 'Sergio Guerrero' => [1, 1210, 1210],
                    'Angel Vazquez' => [1, 1185, 1185], 'Eduardo Vega' => [1, 1110, 1110], 'Alvaro Tarifa' => [1, 825, 825],
                    'Ismael Sierra' => [1, 815, 815], 'Fernando Díaz' => [1, 785, 785], 'Jorge Chorro' => [1, 770, 770],
                    'Cristofer Pérez' => [1, 725, 725], 'Rafael Tomé' => [1, 545, 545],
                    // Bolos: fueron y no pescaron (30). En la hoja, 26,5 cada uno: (11 + 1 + 41) / 2.
                    'Agustín Gomez Maya' => [0, 0, 0], 'Alberto Enrique' => [0, 0, 0], 'Álvaro González' => [0, 0, 0],
                    'Antonio Calvo' => [0, 0, 0], 'Blanca García' => [0, 0, 0], 'Carlos Mateos' => [0, 0, 0],
                    'David Gomez' => [0, 0, 0], 'David Granado' => [0, 0, 0], 'David Machuca' => [0, 0, 0],
                    'Diego Ruiz' => [0, 0, 0], 'Eduardo Soria' => [0, 0, 0], 'Fco. Javier Moreno' => [0, 0, 0],
                    'Felix Daniel Rodriguez' => [0, 0, 0], 'Francisco Dorado' => [0, 0, 0], 'Ismael Arroyo' => [0, 0, 0],
                    'Javier Casas' => [0, 0, 0], 'Javier Soria' => [0, 0, 0], 'Jose Benitez' => [0, 0, 0],
                    'Jose María Lopez' => [0, 0, 0], 'Josué Mateos' => [0, 0, 0], 'Juan Pedro Jerez' => [0, 0, 0],
                    'Lukas Cuadrado' => [0, 0, 0], 'Manuel Martín' => [0, 0, 0], 'Miguel A. de Marcos' => [0, 0, 0],
                    'Miguel Marín' => [0, 0, 0], 'Oscar Hidalgo' => [0, 0, 0], 'Oscar Ramos' => [0, 0, 0],
                    'Pablo Liberal' => [0, 0, 0], 'Rufino Gomez Maya' => [0, 0, 0], 'Victor Moreno' => [0, 0, 0],
                    // No fueron (48): David Basart, David Garrido, Diego Magro, Jorge Kopa, Pablo Mediano, Pedro Yuste.
                ],
            ],
            [
                'nombre' => 'II Manga', 'fecha' => '2026-06-25', 'lugar' => 'Por confirmar',
                'notas' => 'Fecha y embalse por confirmar: la hoja de esta manga no los trae; la fecha es la de la clasificación general provisional (25/6/2026).',
                'resultados' => [
                    'Fernando Díaz' => [5, 5225, 1525], 'Angel Vazquez' => [3, 3250, 1250], 'Victor Moreno' => [4, 2980, 830],
                    'Fco. Javier Moreno' => [4, 2860, 985], 'Rafael Tomé' => [3, 2635, 1265], 'Francisco Dorado' => [2, 1355, 855],
                    'Jorge Kopa' => [2, 1250, 845], 'Manuel Martín' => [3, 1130, 460], 'Ismael Arroyo' => [3, 1040, 405],
                    'Diego Ruiz' => [1, 1015, 1015], 'Miguel García' => [1, 995, 995], 'David Machuca' => [2, 980, 650],
                    'David Granado' => [2, 770, 430], 'Felix Daniel Rodriguez' => [1, 750, 750], 'Alberto Enrique' => [2, 740, 460],
                    'Adrián Fernandez' => [1, 675, 675], 'Antonio Calvo' => [1, 480, 480], 'Oscar Hidalgo' => [1, 435, 435],
                    'Pedro Yuste' => [1, 435, 435], 'Cristofer Pérez' => [1, 395, 395], 'Juan Pedro Jerez' => [1, 260, 260],
                    // Bolos (8): 25,5 cada uno: (21 + 1 + 29) / 2.
                    'Eduardo Vega' => [0, 0, 0], 'Alvaro Tarifa' => [0, 0, 0], 'Eduardo Soria' => [0, 0, 0], 'Javier Soria' => [0, 0, 0],
                    'Jose María Lopez' => [0, 0, 0], 'Josué Mateos' => [0, 0, 0], 'Oscar Ramos' => [0, 0, 0], 'Pablo Liberal' => [0, 0, 0],
                    // No fueron (48): los otros 18.
                ],
            ],
        ];
    }

    public function run(): void
    {
        $club = Club::where('slug', BassExtremaduraSeeder::SLUG)->firstOrFail();
        $temporada = $club->temporadaActiva();
        $orilla = Seccion::where('club_id', $club->id)->where('slug', 'orilla')->firstOrFail();
        $socios = Socio::where('club_id', $club->id)->get()->keyBy('nombre');

        foreach (self::mangas() as $def) {
            if ($orilla->mangas()->whereDate('fecha', $def['fecha'])->exists()) {
                continue;
            }

            $manga = Manga::create([
                'temporada_id' => $temporada->id,
                'seccion_id' => $orilla->id,
                'nombre' => $def['nombre'],
                'fecha' => $def['fecha'],
                'lugar' => $def['lugar'],
                'notas' => $def['notas'],
                'estado' => Manga::ESTADO_CELEBRADA,
            ]);

            foreach ($def['resultados'] as $nombre => [$piezas, $gramos, $mayor]) {
                $socio = $socios[$nombre] ?? throw new \RuntimeException("Socio no encontrado: {$nombre}");

                $participacion = Participacion::create([
                    'manga_id' => $manga->id,
                    'socio_id' => $socio->id,
                    'seccion_id' => $orilla->id,
                    'plica' => true,
                    'pieza_mayor_gramos' => $mayor ?: null,
                ]);

                if ($piezas > 0) {
                    Captura::create(['participacion_id' => $participacion->id, 'piezas' => $piezas, 'peso_gramos' => $gramos]);
                }
            }
        }
    }
}
