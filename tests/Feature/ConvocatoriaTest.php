<?php

namespace Tests\Feature;

use App\Filament\App\Pages\Inicio;
use App\Filament\Resources\Mangas\Pages\EditManga;
use App\Filament\Resources\Mangas\Pages\PesajeManga;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Compartir;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Convocatoria y «asistiré»: el socio dice que irá, el admin lo ve y decide
 * quién ha venido de verdad. La intención nunca puntúa.
 */
class ConvocatoriaTest extends TestCase
{
    use RefreshDatabase;

    private Manga $proxima;

    private Socio $mario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->proxima = Manga::where('estado', Manga::ESTADO_PROGRAMADA)->firstOrFail();
        $this->proxima->update(['ubicacion_url' => 'https://maps.app.goo.gl/ejemplo']);
        $this->mario = Socio::where('email', 'socio@plica.test')->firstOrFail();
    }

    public function test_el_socio_marca_asistire_desde_su_panel_y_puede_quitarlo(): void
    {
        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('app'));

        $componente = Livewire::test(Inicio::class)
            ->assertSee('Asistiré')
            ->assertSee('0 confirmados')
            ->call('confirmar', $this->proxima->id)
            ->assertSee('✓ Asistiré')
            ->assertSee('1 confirmado');

        $this->assertTrue($this->proxima->confirmadoPor($this->mario));
        // Es intención, no asistencia: no hay participación ni puntos.
        $this->assertSame(0, $this->proxima->participacions()->count());

        $componente->call('confirmar', $this->proxima->id);
        $this->assertFalse($this->proxima->confirmadoPor($this->mario));
    }

    public function test_desde_el_enlace_publico_el_socio_confirma_y_el_de_fuera_ve_quien_va(): void
    {
        $url = $this->proxima->urlPublica();

        // Sin cuenta: ve la convocatoria y la invitación a entrar.
        $this->get($url)->assertOk()
            ->assertSee('Cómo llegar')
            ->assertSee('Sé el primero')
            ->assertSee('Entra y confirma');

        // Como socio: confirma con un toque.
        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail());
        $this->post(route('club.manga.asistire', ['club' => 'cd-pesca-piloto', 'manga' => $this->proxima->id]))
            ->assertRedirect($url)
            ->assertSessionHas('asistencia');
        $this->assertTrue($this->proxima->confirmadoPor($this->mario));

        $this->get($url)->assertOk()
            ->assertSee('1 confirmado')
            ->assertSee('Mario López')
            ->assertSee('toca para cancelar');

        // Un socio de otro club no puede confirmar en este.
        $otro = Club::create(['nombre' => 'Otro', 'slug' => 'otro']);
        $intruso = User::create(['name' => 'Intruso', 'email' => 'intruso@otro.test', 'password' => 'secreto123', 'club_id' => $otro->id, 'role' => User::ROLE_SOCIO]);
        Socio::create(['club_id' => $otro->id, 'nombre' => 'Intruso', 'user_id' => $intruso->id]);
        $this->flushSession();
        $this->actingAs($intruso)
            ->post(route('club.manga.asistire', ['club' => 'cd-pesca-piloto', 'manga' => $this->proxima->id]))
            ->assertForbidden();
    }

    public function test_el_admin_ve_los_confirmados_y_al_pasar_lista_ya_vienen_marcados(): void
    {
        $paco = Socio::where('nombre', 'Paco Jiménez')->firstOrFail();
        $this->proxima->alternarConfirmacion($this->mario);
        $this->proxima->alternarConfirmacion($paco);

        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get('/admin/mangas')->assertOk()->assertSee('2 confirmados');
        $this->get("/admin/mangas/{$this->proxima->id}/pesaje")->assertOk()
            ->assertSee('2 confirmaron que vendrían')
            ->assertSee('Mario López, Paco Jiménez');

        // El checklist de asistencia arranca con los confirmados; el admin decide.
        Livewire::test(PesajeManga::class, ['record' => $this->proxima->getRouteKey()])
            ->mountAction('asistencia')
            ->assertActionDataSet(['socios' => [(string) $this->mario->id, (string) $paco->id]])
            ->setActionData(['socios' => [(string) $paco->id]])
            ->callMountedAction();

        // Solo Paco ha venido de verdad: solo él tiene participación.
        $this->assertSame([$paco->id], $this->proxima->participacions()->pluck('socio_id')->all());
    }

    public function test_la_convocatoria_lleva_fecha_lugar_ubicacion_y_enlace_y_se_puede_retocar(): void
    {
        $texto = Compartir::textoConvocatoria($this->proxima);

        $this->assertStringContainsString('Buenas a todos', $texto);
        $this->assertStringContainsString($this->proxima->fecha->locale('es')->isoFormat('dddd D [de] MMMM'), $texto);
        $this->assertStringContainsString('«3ª Manga»', $texto);
        $this->assertStringContainsString('en Embalse de Entrepeñas', $texto);
        $this->assertStringContainsString('📍 Cómo llegar: https://maps.app.goo.gl/ejemplo', $texto);
        $this->assertStringContainsString($this->proxima->urlPublica(), $texto);

        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $componente = Livewire::test(EditManga::class, ['record' => $this->proxima->getRouteKey()])
            ->assertActionVisible('convocar')
            ->mountAction('convocar');

        $modal = (string) $componente->instance()->getMountedAction()->getModalContent();
        $this->assertStringContainsString('<textarea', $modal);
        $this->assertStringContainsString('Buenas a todos', $modal);
        $this->assertStringContainsString('Compartir por WhatsApp', $modal);

        // Una manga celebrada ya no se convoca.
        $celebrada = Manga::where('estado', Manga::ESTADO_CELEBRADA)->firstOrFail();
        Livewire::test(EditManga::class, ['record' => $celebrada->getRouteKey()])->assertActionHidden('convocar');
    }

    public function test_la_ubicacion_se_guarda_desde_el_formulario_y_tiene_que_ser_un_enlace(): void
    {
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditManga::class, ['record' => $this->proxima->getRouteKey()])
            ->fillForm(['ubicacion_url' => 'esto no es un enlace'])
            ->call('save')
            ->assertHasFormErrors(['ubicacion_url' => 'url']);

        Livewire::test(EditManga::class, ['record' => $this->proxima->getRouteKey()])
            ->fillForm(['ubicacion_url' => 'https://maps.app.goo.gl/otro'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('https://maps.app.goo.gl/otro', $this->proxima->fresh()->ubicacion_url);
        $this->assertNotNull(Temporada::first());
    }
}
