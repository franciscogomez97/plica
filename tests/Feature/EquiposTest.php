<?php

namespace Tests\Feature;

use App\Filament\Resources\Equipos\Pages\CreateEquipo;
use App\Filament\Resources\Equipos\Pages\ListEquipos;
use App\Filament\Resources\Seccions\Pages\EditSeccion;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Secciones por equipos (embarcación, carpfishing): la plica es del equipo,
 * fijo toda la temporada. Paso 1: la modalidad en la sección y los equipos
 * con su alta pegando la lista.
 */
class EquiposTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Seccion $embarcacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $this->embarcacion = Seccion::where('nombre', 'Embarcación')->firstOrFail();
        $this->embarcacion->update(['modalidad' => Seccion::MODALIDAD_EQUIPOS, 'tamano_equipo' => 2]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@plica.test')->firstOrFail();
    }

    public function test_una_seccion_puede_ser_por_equipos_y_la_frase_de_reglas_lo_dice(): void
    {
        $this->assertTrue($this->embarcacion->esPorEquipos());
        $this->assertFalse(Seccion::where('nombre', 'Orilla')->firstOrFail()->esPorEquipos());
        $this->assertStringStartsWith('Se pesca por equipos de 2: la plica es del equipo', $this->embarcacion->resumenReglas());
        $this->assertStringNotContainsString('equipos', Seccion::where('nombre', 'Orilla')->firstOrFail()->resumenReglas());

        // En el formulario de la sección se elige quién pesca, y el listado lo enseña.
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get("/admin/seccions/{$this->embarcacion->id}/edit")->assertOk()
            ->assertSee('¿Quién pesca?')
            ->assertSee('Por equipos (barcos, parejas…)')
            ->assertSee('Personas por equipo');
        $this->get('/admin/seccions')->assertOk()->assertSee('equipos de 2');

        Livewire::test(EditSeccion::class, ['record' => $this->embarcacion->getRouteKey()])
            ->fillForm(['modalidad' => Seccion::MODALIDAD_EQUIPOS, 'tamano_equipo' => 3])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(3, $this->embarcacion->fresh()->tamano_equipo);
    }

    public function test_los_equipos_se_dan_de_alta_pegando_la_lista(): void
    {
        $temporada = $this->club->temporadaActiva();

        $resultado = $this->club->altaDeEquipos(<<<'TXT'
            Mario López / Sergio del Río
            Los Lucios: Alberto Rey, Toni Salgado

            3. Nuevo Uno / Nuevo Dos / Nuevo Tres
            - Mario López / Nuevo Cuatro
            Jorge Vidal / Jorge Vidal
            TXT, $this->embarcacion, $temporada);

        $this->assertSame(['Mario López / Sergio del Río', 'Los Lucios', 'Nuevo Dos / Nuevo Tres / Nuevo Uno'], $resultado['creados']);
        $this->assertSame(['Nuevo Uno', 'Nuevo Dos', 'Nuevo Tres'], $resultado['sociosNuevos']);
        $this->assertSame([
            'Mario López ya está en otro equipo de Embarcación',
            'Jorge Vidal aparece dos veces en el mismo equipo',
        ], $resultado['errores']);

        // Nadie de la línea rechazada se ha creado, ni siquiera el socio nuevo.
        $this->assertNull(Socio::where('nombre', 'Nuevo Cuatro')->first());
        $this->assertSame(3, Equipo::count());

        // Los socios del equipo quedan apuntados a la sección, y el nombre es opcional.
        $lucios = Equipo::where('nombre', 'Los Lucios')->firstOrFail();
        $this->assertSame('Alberto Rey / Toni Salgado', $lucios->miembrosTexto());
        $this->assertTrue(Socio::where('nombre', 'Toni Salgado')->firstOrFail()->seccions->contains($this->embarcacion));
        $sinNombre = Equipo::whereNull('nombre')->first();
        $this->assertSame('Mario López / Sergio del Río', $sinNombre->etiqueta());

        // Un socio solo tiene un equipo por sección y temporada.
        $mario = Socio::where('nombre', 'Mario López')->firstOrFail();
        $this->assertSame($sinNombre->id, Equipo::equipoDe($mario, $this->embarcacion, $temporada)?->id);
    }

    public function test_el_menu_equipos_solo_sale_si_el_club_tiene_secciones_por_equipos(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // El menú lo pinta Filament aparte del HTML de la página: se comprueba en la navegación del panel.
        $etiquetas = fn (): array => collect(Filament::getPanel('admin')->getNavigation())
            ->flatMap(fn ($grupo) => collect($grupo->getItems())->map(fn ($item) => $item->getLabel()))
            ->values()->all();
        $this->assertContains('Equipos', $etiquetas());
        $this->get('/admin/equipos')->assertOk()->assertSee('los equipos son fijos toda la temporada');

        // Sin secciones por equipos, el recurso no se registra (la navegación ya construida se memoriza por petición).
        $this->embarcacion->update(['modalidad' => Seccion::MODALIDAD_INDIVIDUAL]);
        $this->assertFalse(\App\Filament\Resources\Equipos\EquipoResource::shouldRegisterNavigation());
    }

    public function test_la_pagina_de_equipos_lista_los_de_la_temporada_activa_y_crea_desde_el_formulario(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $temporada = $this->club->temporadaActiva();
        $this->club->altaDeEquipos("Mario López / Sergio del Río\nLos Lucios: Alberto Rey / Toni Salgado", $this->embarcacion, $temporada);

        $this->get('/admin/equipos')->assertOk()
            ->assertSee('Mario López / Sergio del Río')
            ->assertSee('Los Lucios')
            ->assertSee('2 de 2')
            ->assertSee('Añadir varios');

        // Desde el formulario: la temporada la pone sola, y un socio ya en otro equipo no vale.
        $paco = Socio::where('nombre', 'Paco Jiménez')->firstOrFail();
        $ivan = Socio::where('nombre', 'Iván Perea')->firstOrFail();
        Livewire::test(CreateEquipo::class)
            ->fillForm(['seccion_id' => $this->embarcacion->id, 'nombre' => 'Barco 3', 'socios' => [$paco->id, $ivan->id]])
            ->call('create')
            ->assertHasNoFormErrors();
        $barco = Equipo::where('nombre', 'Barco 3')->firstOrFail();
        $this->assertSame($temporada->id, $barco->temporada_id);
        $this->assertSame('Iván Perea / Paco Jiménez', $barco->miembrosTexto());

        $mario = Socio::where('nombre', 'Mario López')->firstOrFail();
        Livewire::test(CreateEquipo::class)
            ->fillForm(['seccion_id' => $this->embarcacion->id, 'socios' => [$mario->id]])
            ->call('create')
            ->assertHasFormErrors(['socios']);

        // Añadir varios desde la página, con la sección de la pestaña.
        Livewire::test(ListEquipos::class)
            ->callAction('varios', ['seccion_id' => $this->embarcacion->id, 'lista' => 'Andrés Molina / Chema Ortiz'])
            ->assertNotified();
        $this->assertSame(4, Equipo::count());
    }

    public function test_un_club_de_orilla_no_nota_nada(): void
    {
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $this->assertSame(Seccion::MODALIDAD_INDIVIDUAL, $orilla->modalidad);
        $this->assertSame(2, $orilla->tamano_equipo);
        $this->get('/c/cd-pesca-piloto/orilla')->assertOk()->assertDontSee('equipos');
    }
}
