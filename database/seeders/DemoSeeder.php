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
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Datos de DEMO para desarrollo y tests. NUNCA en producción:
     * producción arranca vacía y los clubes se dan de alta a mano (fase piloto).
     */
    public function run(): void
    {
        $club = Club::create([
            'nombre' => 'CD Pesca Piloto',
            'slug' => 'cd-pesca-piloto',
            'localidad' => 'Madrid',
            'descripcion' => 'Club de demo para el piloto de Plica. Estos datos son de ejemplo.',
            'email_contacto' => 'info@cdpescapiloto.test',
            'perfil_publico' => true,
        ]);

        $secciones = collect([
            ['nombre' => 'Orilla', 'criterio' => Seccion::CRITERIO_PESO],
            ['nombre' => 'Embarcación', 'criterio' => Seccion::CRITERIO_PESO],
            ['nombre' => 'Pato — Lucio', 'criterio' => Seccion::CRITERIO_MEDIDA],
        ])->map(fn (array $def) => Seccion::create(['club_id' => $club->id, ...$def]));

        $temporada = Temporada::create(['club_id' => $club->id, 'nombre' => 'Temporada 2026', 'activa' => true]);

        User::create([
            'name' => 'Admin del club',
            'email' => 'admin@plica.test',
            'password' => Hash::make('plica2026'),
            'club_id' => $club->id,
            'role' => User::ROLE_ADMIN,
        ])->forceFill(['password_cambiada_at' => now(), 'guia_completada_at' => now()])->save();

        $nombres = [
            'Mario López', 'Paco Jiménez', 'Andrés Molina', 'Sergio del Río',
            'Rubén Castaño', 'Iván Perea', 'Toni Salgado', 'Jorge Vidal',
            'Dani Cuesta', 'Alberto Rey', 'Chema Ortiz', 'Luis Barranco',
        ];

        $socios = collect($nombres)->map(fn (string $nombre) => Socio::create([
            'club_id' => $club->id,
            'nombre' => $nombre,
        ]));

        // Mario tiene cuenta (para probar el panel de socio).
        $userSocio = User::create([
            'name' => 'Mario López',
            'email' => 'socio@plica.test',
            'password' => Hash::make('plica2026'),
            'club_id' => $club->id,
            'role' => User::ROLE_SOCIO,
        ]);
        $userSocio->forceFill(['password_cambiada_at' => now(), 'guia_completada_at' => now()])->save();
        $socios[0]->update(['user_id' => $userSocio->id, 'email' => 'socio@plica.test']);

        // Cada sección tiene su calendario: dos mangas celebradas y la próxima en
        // dos semanas, por sección. Fechas relativas a hoy: los tests asumen que no
        // hay mangas pasadas sin gestionar y la demo enseña siempre una manga futura.
        $mangasDef = [
            ['nombre' => '1ª Manga', 'fecha' => today()->subWeeks(12), 'lugar' => 'Embalse de San Juan', 'estado' => Manga::ESTADO_CELEBRADA],
            ['nombre' => '2ª Manga', 'fecha' => today()->subWeeks(8), 'lugar' => 'Pantano de Buendía', 'estado' => Manga::ESTADO_CELEBRADA],
            ['nombre' => '3ª Manga', 'fecha' => today()->addWeeks(2), 'lugar' => 'Embalse de Entrepeñas', 'estado' => Manga::ESTADO_PROGRAMADA],
        ];

        // [índice de socio => [piezas, gramos, milímetros, pieza mayor en gramos]] — datos de ejemplo deterministas.
        // El socio de índice i compite en la sección i % 3 (Orilla, Embarcación, Pato — Lucio; esta última por medida).
        $resultados = [
            0 => [[3, 4350, null, 2100], [2, 2100, null, 1300]],
            1 => [[1, 980, null], [4, 5230, null, 2200]],
            2 => [[1, 0, 1205], [0, 0, null]],
            3 => [[2, 3400, null, 1900], [3, 2980, null, 1500]],
            4 => [[0, 0, null], [1, 1150, null]],
            5 => [[1, 0, 710], [1, 0, 450]],
            6 => [[1, 720, null], [0, 0, null]],
            7 => [[2, 2540, null, 1600], [5, 7300, null, 2400]],
            8 => [[1, 0, 930], [1, 0, 850]],
            9 => [[0, 0, null], [2, 2650, null, 1700]],
        ];

        foreach ($mangasDef as $i => $def) {
            foreach ($secciones as $s => $seccion) {
                $manga = Manga::create(['temporada_id' => $temporada->id, 'seccion_id' => $seccion->id, ...$def]);

                if ($def['estado'] !== Manga::ESTADO_CELEBRADA) {
                    continue;
                }

                foreach ($resultados as $socioIdx => $porManga) {
                    if ($socioIdx % 3 !== $s) {
                        continue;
                    }

                    [$piezas, $gramos, $milimetros, $mayor] = array_pad($porManga[$i], 4, null);

                    $participacion = Participacion::create([
                        'manga_id' => $manga->id,
                        'socio_id' => $socios[$socioIdx]->id,
                        'seccion_id' => $seccion->id,
                        'plica' => true,
                        'pieza_mayor_gramos' => $mayor,
                    ]);

                    if ($gramos > 0 || $milimetros !== null) {
                        Captura::create([
                            'participacion_id' => $participacion->id,
                            'piezas' => $piezas,
                            'peso_gramos' => $gramos,
                            'medida_mm' => $milimetros,
                        ]);
                    }
                }
            }
        }
    }
}
