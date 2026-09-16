<?php

namespace Tests\Feature;

use App\Filament\Resources\Mangas\Pages\PesajeManga;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\User;
use App\Services\Compartir;
use App\Services\Scoring;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El domingo del presidente, revisado el 16 de septiembre de 2026: pasar lista
 * sin apuntar al club entero por error, una botonera que decide por él según
 * el momento, y un texto de WhatsApp sin guiones misteriosos.
 */
class DomingoDelPresidenteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_pasar_lista_solo_ensena_a_los_de_la_seccion_salvo_que_se_pidan_los_demas(): void
    {
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $manga = Manga::create(['temporada_id' => $orilla->club->temporadaActiva()->id, 'seccion_id' => $orilla->id, 'nombre' => 'Domingo', 'fecha' => today(), 'estado' => Manga::ESTADO_PROGRAMADA]);
        $deOrilla = $orilla->socios()->pluck('socios.id');
        $forastero = Socio::where('club_id', $orilla->club_id)->whereNotIn('id', $deOrilla)->firstOrFail();
        $this->assertGreaterThan(0, $deOrilla->count());

        // Sin el interruptor: solo los de la sección. «Seleccionar todos» no puede apuntar a nadie más.
        $sinOtras = \App\Filament\Resources\Mangas\Actions\AsistenciaAction::opciones($manga, false);
        $this->assertEqualsCanonicalizing($deOrilla->all(), array_keys($sinOtras));
        $this->assertArrayNotHasKey($forastero->id, $sinOtras);

        // Con el interruptor, aparecen los demás marcados como «otra sección».
        $conOtras = \App\Filament\Resources\Mangas\Actions\AsistenciaAction::opciones($manga, true);
        $this->assertSame($forastero->nombre.' (otra sección)', $conOtras[$forastero->id]);
        $this->assertGreaterThan(count($sinOtras), count($conOtras));

        // Quien dijo «asistiré» sale siempre, sea de donde sea.
        $manga->confirmacions()->create(['socio_id' => $forastero->id]);
        $this->assertArrayHasKey($forastero->id, \App\Filament\Resources\Mangas\Actions\AsistenciaAction::opciones($manga->fresh(), false));
        $manga->confirmacions()->delete();

        // Quien ya está apuntado, aunque sea de otra sección, sale siempre (si no, guardar lo quitaría).
        $manga->participacions()->create(['socio_id' => $forastero->id, 'seccion_id' => $orilla->id]);
        $this->assertArrayHasKey($forastero->id, \App\Filament\Resources\Mangas\Actions\AsistenciaAction::opciones($manga->fresh(), false));
        Livewire::test(PesajeManga::class, ['record' => $manga->getRouteKey()])
            ->mountAction('asistencia')
            ->callMountedAction();
        $this->assertTrue($manga->participacions()->where('socio_id', $forastero->id)->exists(), 'guardar sin tocar nada no lo quita');
    }

    public function test_la_botonera_del_pesaje_cambia_con_el_momento(): void
    {
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $temporada = $orilla->club->temporadaActiva();

        // Antes de la manga: convocar en verde, pasar lista en gris.
        $futura = Manga::create(['temporada_id' => $temporada->id, 'seccion_id' => $orilla->id, 'nombre' => 'Futura', 'fecha' => today()->addWeek(), 'estado' => Manga::ESTADO_PROGRAMADA]);
        Livewire::test(PesajeManga::class, ['record' => $futura->getRouteKey()])
            ->assertActionVisible('convocar')
            ->assertActionHasColor('convocar', 'success')
            ->assertActionHasLabel('asistencia', 'Pasar lista')
            ->assertActionHasColor('asistencia', 'gray');

        // El domingo por la tarde, sin nadie apuntado: convocar ya no sirve; pasar lista es lo que destaca.
        $pasada = Manga::create(['temporada_id' => $temporada->id, 'seccion_id' => $orilla->id, 'nombre' => 'De ayer', 'fecha' => today()->subDay(), 'estado' => Manga::ESTADO_PROGRAMADA]);
        Livewire::test(PesajeManga::class, ['record' => $pasada->getRouteKey()])
            ->assertActionHidden('convocar')
            ->assertActionHasColor('asistencia', 'success')
            ->assertActionHasColor('clasificacion', 'gray')
            // La pantalla vacía nombra el botón por el nombre que tiene en ese momento.
            ->assertSee('«Pasar lista» apunta a varios de golpe')
            ->assertDontSee('«Marcar asistencia»');

        // Con gente pesada: la clasificación en verde, la asistencia vuelve a gris.
        $pasada->sincronizarAsistencia($orilla->socios()->pluck('socios.id')->take(3)->all());
        Livewire::test(PesajeManga::class, ['record' => $pasada->getRouteKey()])
            ->assertActionHasLabel('asistencia', 'Marcar asistencia')
            ->assertActionHasColor('asistencia', 'gray')
            ->assertActionHasColor('clasificacion', 'success');
    }

    public function test_el_bolo_en_el_texto_de_whatsapp_dice_0_kg(): void
    {
        // Dos que pescan y uno que hace bolo: el bolo está en el podio del texto.
        $escenario = \Tests\Support\Escenario::crear(['Ana' => [3200], 'Bea' => [1500], 'Rubén Castaño' => ['bolo']], [], 'domingo-bolo');
        $manga = $escenario->mangas->first();
        $texto = Compartir::textoManga($manga, Scoring::clasificacionManga($manga));

        $this->assertStringNotContainsString(' · —', $texto);
        $this->assertStringContainsString('3º Rubén Castaño · 0 kg', $texto);
        $this->assertSame('0 kg', Scoring::valorPrincipalOCero(Seccion::CRITERIO_PESO, (object) ['peso' => 0, 'piezas' => 0, 'medida' => 0]));
        $this->assertSame('0 cm', Scoring::valorPrincipalOCero(Seccion::CRITERIO_MEDIDA, (object) ['peso' => 0, 'piezas' => 0, 'medida' => 0]));
        $this->assertSame('3,450 kg', Scoring::valorPrincipalOCero(Seccion::CRITERIO_PESO, (object) ['peso' => 3450, 'piezas' => 2, 'medida' => 0]));
    }
}
