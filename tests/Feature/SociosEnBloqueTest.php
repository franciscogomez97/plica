<?php

namespace Tests\Feature;

use App\Filament\Resources\Socios\Pages\ListSocios;
use App\Models\Club;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Alta de socios pegando una lista: los clubes no tienen CSV. */
class SociosEnBloqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_una_lista_pegada_del_whatsapp_da_de_alta_a_los_socios(): void
    {
        $club = Club::create(['nombre' => 'Club nuevo', 'slug' => 'club-nuevo']);

        $resultado = $club->altaDeSocios(<<<'TXT'
            Mario López 600 11 22 33
            Paco Jiménez, paco@gmail.com

            3. Andrés  Molina
            - Toni Salgado toni@salgado.es
            • Chema Ortiz;
            Rubén Castaño, 611 22 33 44, ruben@castano.es
            Iván Perea ivan@perea.es +34 622 33 44 55
            TXT);

        $this->assertSame(['Mario López', 'Paco Jiménez', 'Andrés Molina', 'Toni Salgado', 'Chema Ortiz', 'Rubén Castaño', 'Iván Perea'], $resultado['creados']);
        $this->assertSame([], $resultado['repetidos']);

        $this->assertSame(7, $club->socios()->count());
        $this->assertSame('paco@gmail.com', $club->socios()->where('nombre', 'Paco Jiménez')->value('email'));
        $this->assertSame('toni@salgado.es', $club->socios()->where('nombre', 'Toni Salgado')->value('email'));
        $this->assertNull($club->socios()->where('nombre', 'Mario López')->value('email'));

        // El teléfono, en cualquier orden respecto al email, tal como se tecleó.
        $this->assertSame('600 11 22 33', $club->socios()->where('nombre', 'Mario López')->value('telefono'));
        $this->assertSame('611 22 33 44', $club->socios()->where('nombre', 'Rubén Castaño')->value('telefono'));
        $this->assertSame('ruben@castano.es', $club->socios()->where('nombre', 'Rubén Castaño')->value('email'));
        $this->assertSame('+34 622 33 44 55', $club->socios()->where('nombre', 'Iván Perea')->value('telefono'));
        $this->assertSame('ivan@perea.es', $club->socios()->where('nombre', 'Iván Perea')->value('email'));
        $this->assertNull($club->socios()->where('nombre', 'Paco Jiménez')->value('telefono'));
    }

    public function test_los_que_ya_estan_no_se_duplican_aunque_cambien_mayusculas_o_tildes(): void
    {
        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $antes = $club->socios()->count();

        $resultado = $club->altaDeSocios("mario lopez\nPACO JIMÉNEZ\nSocio Nuevo\nsocio nuevo");

        $this->assertSame(['Socio Nuevo'], $resultado['creados']);
        $this->assertSame(['mario lopez', 'PACO JIMÉNEZ', 'socio nuevo'], $resultado['repetidos']);
        $this->assertSame($antes + 1, $club->socios()->count());
    }

    public function test_la_accion_del_listado_da_de_alta_en_el_club_del_admin(): void
    {
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListSocios::class)
            ->callAction('varios', ['lista' => "Nuevo Uno\nNuevo Dos, dos@club.es"])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $this->assertDatabaseHas('socios', ['club_id' => $club->id, 'nombre' => 'Nuevo Uno']);
        $this->assertDatabaseHas('socios', ['club_id' => $club->id, 'nombre' => 'Nuevo Dos', 'email' => 'dos@club.es']);
    }
}
