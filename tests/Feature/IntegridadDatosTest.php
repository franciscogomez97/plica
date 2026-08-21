<?php

namespace Tests\Feature;

use App\Models\Manga;
use App\Models\Socio;
use App\Models\Temporada;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La base de datos protege el historial aunque la aplicación falle. */
class IntegridadDatosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_un_socio_con_historial_no_puede_borrarse(): void
    {
        $socio = Socio::has('participacions')->firstOrFail();

        $this->expectException(QueryException::class);
        $socio->delete();
    }

    public function test_un_socio_sin_historial_si_puede_borrarse(): void
    {
        $socio = Socio::doesntHave('participacions')->firstOrFail();

        $socio->delete();
        $this->assertDatabaseMissing('socios', ['id' => $socio->id]);
    }

    public function test_una_temporada_con_mangas_no_puede_borrarse(): void
    {
        $temporada = Temporada::has('mangas')->firstOrFail();

        $this->expectException(QueryException::class);
        $temporada->delete();
    }

    public function test_solo_una_temporada_activa_por_club(): void
    {
        $original = Temporada::where('activa', true)->firstOrFail();

        $nueva = Temporada::create([
            'club_id' => $original->club_id,
            'nombre' => 'Temporada 2027',
            'activa' => true,
        ]);

        $this->assertFalse($original->fresh()->activa);
        $this->assertTrue($nueva->fresh()->activa);
        $this->assertSame(1, Temporada::where('club_id', $original->club_id)->where('activa', true)->count());
    }

    public function test_borrar_una_manga_vacia_si_esta_permitido(): void
    {
        $temporada = Temporada::firstOrFail();
        $manga = Manga::create([
            'temporada_id' => $temporada->id,
            'nombre' => 'Manga errónea',
            'fecha' => today()->addDays(30),
        ]);

        $manga->delete();
        $this->assertDatabaseMissing('mangas', ['id' => $manga->id]);
    }
}
