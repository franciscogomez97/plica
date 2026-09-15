<?php

namespace Tests\Feature;

use App\Models\Seccion;
use App\Services\Scoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\Support\MotorDeReferencia;
use Tests\TestCase;

/**
 * El motor contra TODAS las combinaciones de reglas de una sección, comparado
 * con un motor de referencia escrito aparte (Tests\Support\MotorDeReferencia)
 * sobre varios escenarios: una temporada normal, empates exactos, bolos,
 * alguien que entra a mitad, alguien que no va nunca, mangas guardadas en
 * otro orden. Si el motor real y el de referencia discrepan, uno de los dos
 * lee mal la regla: se mira a mano. Además, en cada combinación se comprueban
 * invariantes que no dependen de la regla (el cuadro cuadra con el ranking,
 * nunca se descarta de más, los puestos se comparten en los empates exactos).
 */
class MotorMatrizTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    //  Escenarios (una línea por socio, una columna por manga; ver Escenario)
    // ------------------------------------------------------------------

    /** Una temporada normal: cuatro mangas, uno que no va a alguna, un bolo, uno que no va nunca. */
    private const NORMAL = [
        'Ana' => [3200, 2100, '—', 4100],
        'Bea' => [1500, 'bolo', 2600, 1900],
        'Cai' => ['—', 900, 3300, '—'],
        'Dan' => [3200, 2100, 1000, 'bolo'],
        'Eli' => [700, 700, 700, 700],
        'Fer' => ['—', '—', '—', '—'],
    ];

    /** Empates exactos en mangas y en el total, con detalle de piezas y pieza mayor para desempatar. */
    private const EMPATES = [
        'Ana' => [['g' => 3000, 'p' => 3, 'mayor' => 1500], ['g' => 2000, 'p' => 2, 'mayor' => 1200], 'bolo'],
        'Bea' => [['g' => 3000, 'p' => 2, 'mayor' => 2000], ['g' => 2000, 'p' => 2, 'mayor' => 1200], 'bolo'],
        'Cai' => [['g' => 3000, 'p' => 3, 'mayor' => 1500], ['g' => 1000, 'p' => 1], ['g' => 1000, 'p' => 1]],
        'Dan' => [['g' => 1000, 'p' => 1], ['g' => 4000, 'p' => 4, 'mayor' => 1000], '—'],
        'Eli' => ['bolo', 'bolo', 'bolo'],
    ];

    /** Una manga en la que todos hacen bolo, otra con uno solo, y alguien que entra en la tercera. */
    private const BORDES = [
        'Ana' => ['bolo', 2500, 1800],
        'Bea' => ['bolo', '—', 1800],
        'Cai' => ['—', '—', 3000],
        'Dan' => ['bolo', '—', '—'],
    ];

    /** Lo mismo que NORMAL, con socios y mangas al revés: el resultado no puede depender del orden de guardado. */
    private static function normalInvertido(): array
    {
        $spec = [];
        foreach (array_reverse(self::NORMAL, true) as $nombre => $celdas) {
            $spec[$nombre] = array_reverse($celdas);
        }

        return $spec;
    }

    // ------------------------------------------------------------------
    //  Configuraciones: el producto cartesiano de las reglas
    // ------------------------------------------------------------------

    /** @return iterable<string, array<string, mixed>> nombre legible => configuración de sección */
    private static function configuraciones(bool $completo): iterable
    {
        foreach ([Seccion::CRITERIO_PESO, Seccion::CRITERIO_MEDIDA, Seccion::CRITERIO_PIEZAS] as $criterio) {
            $desempates = array_keys(Seccion::desempatesPara($criterio, Seccion::SISTEMA_ACUMULADO));
            // Suma lo pescado.
            foreach ([0, 3] as $participacion) {
                foreach ([0, -7] as $ausencia) { // sumando lo pescado, no ir nunca suma (el formulario lo impide)
                    foreach ([0, 1, 2] as $descartes) {
                        foreach ($descartes > 0 ? [false, true] : [false] as $descAus) {
                            foreach ($completo ? $desempates : [Seccion::desempatePorDefecto($criterio), Seccion::DESEMPATE_COMPARTIDO] as $desempate) {
                                yield "acumulado $criterio asist=$participacion aus=$ausencia desc=$descartes".($descAus ? '+aus' : '')." emp=$desempate" => [
                                    'criterio' => $criterio, 'sistema_puntuacion' => Seccion::SISTEMA_ACUMULADO,
                                    'puntos_participacion' => $participacion, 'puntos_no_asistencia' => $ausencia,
                                    'descartes' => $descartes, 'descartes_ausencias' => $descAus, 'desempate' => $desempate,
                                    'bolo' => Seccion::BOLO_MEDIA, 'puntos_bolo' => 0, 'desempate_general' => Seccion::DESEMPATE_COMPARTIDO,
                                ];
                            }
                        }
                    }
                }
            }
            // Por puestos.
            $desempatesPuestos = $completo
                ? array_keys(Seccion::desempatesPara($criterio, Seccion::SISTEMA_PUESTOS))
                : [Seccion::desempatePorDefecto($criterio), Seccion::DESEMPATE_COMPARTIDO, Seccion::DESEMPATE_PROMEDIO];
            $generales = $completo
                ? array_keys(Seccion::desempatesGeneralPara($criterio))
                : [Seccion::DESEMPATE_COMPARTIDO, Seccion::DESEMPATE_GENERAL_MEJOR_MANGA, Seccion::DESEMPATE_PIEZA_MAYOR];
            // Las reglas de la manga (bolo × desempate) y las de la general (desempate general) son
            // independientes: se cruzan cada una con ausencias y descartes, no todas entre sí.
            $bolos = $completo ? array_keys(Seccion::BOLOS) : [Seccion::BOLO_MEDIA, Seccion::BOLO_FIJO];
            $pares = [];
            foreach ($bolos as $bolo) {
                foreach ($desempatesPuestos as $desempate) {
                    $pares[] = [$bolo, $desempate, Seccion::DESEMPATE_COMPARTIDO];
                }
            }
            foreach ($generales as $general) {
                $pares[] = [Seccion::BOLO_MEDIA, Seccion::desempatePorDefecto($criterio), $general];
            }
            foreach ([0, 9] as $ausencia) {
                foreach ([0, 1, 2] as $descartes) {
                    foreach ($descartes > 0 ? [false, true] : [true] as $descAus) {
                        foreach ($pares as [$bolo, $desempate, $general]) {
                            yield "puestos $criterio aus=$ausencia desc=$descartes".($descAus ? '+aus' : '')." bolo=$bolo emp=$desempate gen=$general" => [
                                'criterio' => $criterio, 'sistema_puntuacion' => Seccion::SISTEMA_PUESTOS,
                                'puntos_participacion' => 0, 'puntos_no_asistencia' => $ausencia,
                                'descartes' => $descartes, 'descartes_ausencias' => $descAus, 'desempate' => $desempate,
                                'bolo' => $bolo, 'puntos_bolo' => 4, 'desempate_general' => $general,
                            ];
                        }
                    }
                }
            }
        }
    }

    /** La configuración de sección en el lenguaje del motor de referencia. */
    private static function cfgReferencia(array $seccion): array
    {
        return [
            'criterio' => $seccion['criterio'], 'sistema' => $seccion['sistema_puntuacion'],
            'puntos_participacion' => $seccion['puntos_participacion'], 'puntos_no_asistencia' => $seccion['puntos_no_asistencia'],
            'descartes' => $seccion['descartes'], 'descartes_ausencias' => $seccion['descartes_ausencias'],
            'desempate' => $seccion['desempate'], 'desempate_general' => $seccion['desempate_general'],
            'bolo' => $seccion['bolo'], 'puntos_bolo' => $seccion['puntos_bolo'],
        ];
    }

    // ------------------------------------------------------------------
    //  Los tests
    // ------------------------------------------------------------------

    public function test_temporada_normal_contra_todas_las_combinaciones(): void
    {
        $this->compararTodas(self::NORMAL, completo: true);
    }

    public function test_empates_exactos_contra_todas_las_combinaciones(): void
    {
        $this->compararTodas(self::EMPATES, completo: true);
    }

    public function test_bordes_bolos_generales_y_quien_entra_a_mitad(): void
    {
        $this->compararTodas(self::BORDES, completo: false);
    }

    public function test_el_orden_de_guardado_no_cambia_nada(): void
    {
        $this->compararTodas(self::normalInvertido(), completo: false);
    }

    public function test_un_equipo_de_una_persona_puntua_exactamente_igual_que_esa_persona(): void
    {
        $escenarios = [];
        $n = 0;
        foreach (self::configuraciones(completo: false) as $nombre => $seccion) {
            $escenario = $escenarios[$seccion['criterio']] ??= $this->escenarioPara(self::NORMAL, $seccion['criterio'], 'equipos-uno', fn ($e) => $e->comoEquipos());
            $escenario->configurar($seccion);
            $this->compararConReferencia($escenario, self::NORMAL, $seccion, "[equipos de uno] $nombre");
            $n++;
        }
        $this->assertGreaterThan(50, $n);
    }

    public function test_un_equipo_de_dos_es_una_sola_plica(): void
    {
        // Ana y Bea forman un barco: en cada manga la plica del barco es la primera celda con dato de
        // sus miembros. Eso, como «un socio» del escenario equivalente, da lo mismo que un ranking individual.
        $grupos = ['Ana y Bea' => ['Ana', 'Bea'], 'Cai' => ['Cai'], 'Dan' => ['Dan'], 'Eli' => ['Eli'], 'Fer' => ['Fer']];
        $plicaDelBarco = [];
        foreach ([0, 1, 2, 3] as $i) {
            $plicaDelBarco[$i] = '—';
            foreach (['Ana', 'Bea'] as $m) {
                if ((self::NORMAL[$m][$i] ?? '—') !== '—') {
                    $plicaDelBarco[$i] = self::NORMAL[$m][$i];
                    break;
                }
            }
        }
        $equivalente = ['Ana y Bea' => $plicaDelBarco] + array_slice(self::NORMAL, 2, null, true);
        $escenarios = [];
        foreach (self::configuraciones(completo: false) as $nombre => $seccion) {
            $escenario = $escenarios[$seccion['criterio']] ??= $this->escenarioPara(self::NORMAL, $seccion['criterio'], 'equipos-dos', fn ($e) => $e->comoEquipos($grupos));
            $escenario->configurar($seccion);
            $this->compararConReferencia($escenario, $equivalente, $seccion, "[equipo de dos] $nombre");
        }
    }

    // ------------------------------------------------------------------
    //  Comparación e invariantes
    // ------------------------------------------------------------------

    /** Un escenario por criterio: las capturas se escriben en la unidad del criterio (gramos, mm o piezas) al crearse. */
    private function escenarioPara(array $spec, string $criterio, string $slug, ?callable $preparar = null): Escenario
    {
        $escenario = Escenario::crear($spec, ['criterio' => $criterio], "$slug-$criterio");

        return $preparar ? $preparar($escenario) : $escenario;
    }

    private function compararTodas(array $spec, bool $completo): void
    {
        $slug = 'matriz-'.substr(md5(json_encode($spec).($completo ? '1' : '0')), 0, 8);
        $escenarios = [];
        $n = 0;
        foreach (self::configuraciones($completo) as $nombre => $seccion) {
            $escenario = $escenarios[$seccion['criterio']] ??= $this->escenarioPara($spec, $seccion['criterio'], $slug);
            $escenario->configurar($seccion);
            $this->compararConReferencia($escenario, $spec, $seccion, $nombre);
            $n++;
        }
        $this->assertGreaterThan($completo ? 500 : 50, $n, 'la matriz tiene que recorrer muchas combinaciones');
    }

    private function compararConReferencia(Escenario $escenario, array $spec, array $seccion, string $caso): void
    {
        $esperado = MotorDeReferencia::ranking($spec, self::cfgReferencia($seccion));
        $mangaIndice = $escenario->mangas->map(fn ($m) => $m->id)->flip()->all(); // manga id => índice de columna

        // --- Ranking de temporada: mismo puesto y mismos puntos para cada uno, y las mismas mangas descartadas.
        $grupo = Scoring::rankingTemporada($escenario->temporada)->firstWhere('seccionId', $escenario->seccion->id);
        $real = $grupo->filas->mapWithKeys(fn ($f) => [$f->participante->nombre => ['puesto' => $f->puesto, 'puntos' => $f->puntos]])->all();
        $esperadoPorNombre = collect($esperado['ranking'])->mapWithKeys(fn ($f) => [$f['nombre'] => ['puesto' => $f['puesto'], 'puntos' => $f['puntos']]])->all();
        ksort($real);
        ksort($esperadoPorNombre);
        $this->assertEquals($esperadoPorNombre, $real, "ranking · $caso");

        // --- Cuadro manga a manga: los mismos totales y las mismas mangas tachadas que el ranking.
        $cuadro = Scoring::cuadroSeccion($escenario->temporada, $escenario->seccion);
        $descartadasEsperadas = collect($esperado['ranking'])->mapWithKeys(fn ($f) => [$f['nombre'] => $f['descartadas']])->all();
        foreach ($cuadro->filas as $fila) {
            $nombre = $fila->participante->nombre;
            $this->assertEquals($real[$nombre]['puntos'], $fila->puntos, "cuadro: total de $nombre · $caso");
            $this->assertEquals($real[$nombre]['puesto'], $fila->puesto, "cuadro: puesto de $nombre · $caso");
            $tachadas = collect($fila->descartadas)->map(fn ($id) => $mangaIndice[$id])->sort()->values()->all();
            $this->assertSame(collect($descartadasEsperadas[$nombre])->sort()->values()->all(), $tachadas, "cuadro: descartadas de $nombre · $caso");
            $this->assertLessThanOrEqual($seccion['descartes'], count($tachadas), "nunca se descarta de más · $nombre · $caso");
            $this->assertCount($escenario->mangas->count(), $fila->celdas, "una celda por manga · $nombre · $caso");
        }
        $this->assertCount(count($real), $cuadro->filas, "el cuadro tiene las mismas filas que el ranking · $caso");

        // --- Clasificación de cada manga: los mismos puestos.
        foreach ($escenario->mangas as $i => $manga) {
            $clasif = Scoring::clasificacionManga($manga)->first();
            $puestos = $clasif ? $clasif->filas->mapWithKeys(fn ($f) => [$f->participante->nombre => $f->puesto])->all() : [];
            ksort($puestos);
            $esperadoManga = $esperado['mangas'][$i];
            ksort($esperadoManga);
            $this->assertSame($esperadoManga, $puestos, "clasificación de la manga ".($i + 1)." · $caso");
        }

        // --- Los puestos se comparten en los empates exactos y no hay saltos raros.
        $puestos = $grupo->filas->pluck('puesto')->all();
        foreach ($puestos as $k => $puesto) {
            $this->assertSame($k + 1 === $puesto || ($k > 0 && $puestos[$k - 1] === $puesto), true, "puesto ".($k + 1)." coherente · $caso");
        }
    }
}
