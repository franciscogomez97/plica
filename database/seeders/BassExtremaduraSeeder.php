<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use Illuminate\Database\Seeder;

/**
 * Bass Extremadura: la sección Orilla con su reglamento y sus 47 socios, tal
 * como vienen en la hoja que pasó su directivo en septiembre de 2026. Sin
 * pesajes: las mangas las mete el club. Su sistema es el de federación: cada
 * manga da tantos puntos como tu puesto, no se desempata (los empatados se
 * reparten el promedio), no ir cuesta 48 (47 socios + 1) y gana quien menos suma.
 *
 * Idempotente: encuentra el club por slug (existe en producción, creado vacío),
 * la sección por slug (y deja sus reglas como aquí) y los socios por nombre.
 *
 *   php artisan db:seed --class=BassExtremaduraSeeder --force
 */
class BassExtremaduraSeeder extends Seeder
{
    public const SLUG = 'bass-extremadura';

    /** En el orden de la hoja. «Edurado Vega» de la hoja va como Eduardo Vega. */
    public const SOCIOS = [
        'Fernando Díaz', 'Angel Vazquez', 'Victor Moreno', 'Fco. Javier Moreno', 'Rafael Tomé',
        'Francisco Dorado', 'Jorge Kopa', 'Manuel Martín', 'Ismael Arroyo', 'Diego Ruiz',
        'Miguel García', 'David Machuca', 'David Granado', 'Felix Daniel Rodriguez', 'Alberto Enrique',
        'Adrián Fernandez', 'Antonio Calvo', 'Oscar Hidalgo', 'Pedro Yuste', 'Cristofer Pérez',
        'Juan Pedro Jerez', 'Eduardo Vega', 'Alvaro Tarifa', 'Eduardo Soria', 'Javier Soria',
        'Jose María Lopez', 'Josué Mateos', 'Oscar Ramos', 'Pablo Liberal', 'Sergio Guerrero',
        'Ismael Sierra', 'Jorge Chorro', 'Agustín Gomez Maya', 'Álvaro González', 'Blanca García',
        'Carlos Mateos', 'David Gomez', 'Javier Casas', 'Jose Benitez', 'Lukas Cuadrado',
        'Miguel A. de Marcos', 'Miguel Marín', 'Rufino Gomez Maya', 'David Basart', 'David Garrido',
        'Diego Magro', 'Pablo Mediano',
    ];

    public function run(): void
    {
        $club = Club::firstOrCreate(['slug' => self::SLUG], ['nombre' => 'Bass Extremadura']);

        Temporada::firstOrCreate(['club_id' => $club->id, 'nombre' => 'Temporada '.now()->year], ['activa' => true]);

        // Federación: no se desempata; los empatados se reparten el promedio de sus puestos.
        Seccion::updateOrCreate(['club_id' => $club->id, 'slug' => 'orilla'], [
            'nombre' => 'Orilla',
            'criterio' => Seccion::CRITERIO_PESO,
            'numero_socios' => count(self::SOCIOS),
            'sistema_puntuacion' => Seccion::SISTEMA_PUESTOS,
            'puntos_participacion' => 0,
            'puntos_no_asistencia' => count(self::SOCIOS) + 1,
            'bolo' => Seccion::BOLO_MEDIA, // ((C + 1) + N) / 2, la fórmula que pasó su directivo
            'puntos_bolo' => 0,
            'descartes' => 0,
            'descartes_ausencias' => true, // si algún día descartan, faltar cuenta como la peor manga
            'desempate' => Seccion::DESEMPATE_PROMEDIO,
        ]);

        $orilla = Seccion::where('club_id', $club->id)->where('slug', 'orilla')->firstOrFail();

        foreach (self::SOCIOS as $nombre) {
            $socio = Socio::firstOrCreate(['club_id' => $club->id, 'nombre' => $nombre]);
            // Todos son de Orilla: en su hoja salen los 47, también los que no han ido a ninguna manga (96).
            $socio->seccions()->syncWithoutDetaching([$orilla->id]);
        }
    }
}
