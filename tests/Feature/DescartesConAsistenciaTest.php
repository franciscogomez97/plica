<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Services\Scoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Reproducción: descartes en «suma lo pescado» con puntos por asistencia y castigo por ausencia. */
class DescartesConAsistenciaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Seccion, 1: Temporada, 2: Socio} */
    private function escenario(int $puntosNoAsistencia): array
    {
        $club = Club::create(['nombre' => 'Club', 'slug' => 'club']);
        $temporada = Temporada::create(['club_id' => $club->id, 'nombre' => 'T', 'activa' => true]);
        $seccion = Seccion::create(['club_id' => $club->id, 'nombre' => 'Orilla', 'criterio' => Seccion::CRITERIO_PESO,
            'puntos_participacion' => 1, 'descartes' => 1, 'descartes_ausencias' => true, 'puntos_no_asistencia' => $puntosNoAsistencia]);
        $alberto = Socio::create(['club_id' => $club->id, 'nombre' => 'Alberto']);
        $otro = Socio::create(['club_id' => $club->id, 'nombre' => 'Otro']);

        $primera = Manga::create(['temporada_id' => $temporada->id, 'seccion_id' => $seccion->id, 'nombre' => '1ª', 'fecha' => '2026-03-01', 'estado' => Manga::ESTADO_CELEBRADA]);
        $segunda = Manga::create(['temporada_id' => $temporada->id, 'seccion_id' => $seccion->id, 'nombre' => '2ª', 'fecha' => '2026-04-01', 'estado' => Manga::ESTADO_CELEBRADA]);
        // Alberto va a la 1ª y hace bolo; a la 2ª no va. «Otro» va a las dos (para que existan).
        $primera->participacions()->create(['socio_id' => $alberto->id, 'seccion_id' => $seccion->id]);
        // Participaciones guardadas en orden inverso a la fecha, a propósito.
        $segunda->participacions()->create(['socio_id' => $otro->id, 'seccion_id' => $seccion->id])->capturas()->create(['piezas' => 1, 'peso_gramos' => 1000]);
        $primera->participacions()->create(['socio_id' => $otro->id, 'seccion_id' => $seccion->id])->capturas()->create(['piezas' => 1, 'peso_gramos' => 1000]);

        return [$seccion, $temporada, $alberto];
    }

    public function test_bolo_mas_ausencia_con_ausencia_a_cero(): void
    {
        [$seccion, $temporada, $alberto] = $this->escenario(0);
        $fila = Scoring::rankingTemporada($temporada)->firstWhere('seccionId', $seccion->id)->filas->firstWhere('socio.nombre', 'Alberto');
        // Se descarta la peor: la ausencia (aporta 0) frente al bolo (aporta 0 + 1 de asistencia). Debería dar 1.
        $this->assertSame(1, $fila->puntos, 'ranking');
        $cuadro = Scoring::cuadroSeccion($temporada, $seccion);
        $this->assertSame(1, $cuadro->filas->firstWhere('socio.nombre', 'Alberto')->puntos, 'cuadro');
    }

    public function test_bolo_mas_ausencia_con_castigo(): void
    {
        [$seccion, $temporada, $alberto] = $this->escenario(-500);
        $grupo = Scoring::rankingTemporada($temporada)->firstWhere('seccionId', $seccion->id);
        $fila = $grupo->filas->firstWhere('socio.nombre', 'Alberto');
        $this->assertSame(1, $fila->puntos, 'ranking: la peor manga es la ausencia (-500), no el bolo (+1)');

        $cuadro = Scoring::cuadroSeccion($temporada, $seccion);
        $filaCuadro = $cuadro->filas->firstWhere('socio.nombre', 'Alberto');
        $segunda = Manga::where('nombre', '2ª')->firstOrFail();
        $this->assertSame([$segunda->id], $filaCuadro->descartadas, 'cuadro: tachada la ausencia');
        $this->assertSame($fila->puntos, $filaCuadro->puntos, 'ranking y cuadro deben coincidir');
    }
}
