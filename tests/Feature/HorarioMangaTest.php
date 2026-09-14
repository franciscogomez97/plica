<?php

namespace Tests\Feature;

use App\Filament\App\Pages\Inicio;
use App\Filament\Resources\Mangas\Pages\EditManga;
use App\Filament\Resources\Mangas\Pages\ListMangas;
use App\Models\Manga;
use App\Models\User;
use App\Services\Compartir;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Horario de la manga (empieza y termina) y quedada previa (dónde y a qué
 * hora se junta el club antes de ir al agua). Todo opcional; cuando está
 * puesto sale en la convocatoria, en la página pública, en el Inicio del
 * socio y en los listados del admin.
 */
class HorarioMangaTest extends TestCase
{
    use RefreshDatabase;

    private Manga $proxima;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        // La próxima manga de la demo trae horario y quedada, para que se vea en la convocatoria.
        $this->proxima = Manga::where('estado', Manga::ESTADO_PROGRAMADA)->whereNotNull('hora_inicio')->firstOrFail();
    }

    /** La misma manga, sin horario ni quedada. */
    private function limpia(): Manga
    {
        $this->proxima->update(['hora_inicio' => null, 'hora_fin' => null, 'quedada_lugar' => null, 'quedada_hora' => null, 'quedada_url' => null]);

        return $this->proxima->fresh();
    }

    public function test_la_demo_trae_una_manga_con_horario_y_quedada(): void
    {
        $this->assertSame('de 08:00 a 14:00', $this->proxima->horario());
        $this->assertSame('a las 07:15 en Bar La Presa (Sacedón)', $this->proxima->quedada());
    }

    private function conHorarioYQuedada(): Manga
    {
        $this->proxima->update([
            'hora_inicio' => '08:00', 'hora_fin' => '14:00',
            'quedada_lugar' => 'Bar Manolo', 'quedada_hora' => '07:00', 'quedada_url' => 'https://maps.app.goo.gl/bar',
        ]);

        return $this->proxima->fresh();
    }

    public function test_el_formulario_guarda_horario_y_quedada(): void
    {
        $this->limpia();
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditManga::class, ['record' => $this->proxima->getRouteKey()])
            ->assertFormFieldExists('hora_inicio')
            ->assertFormFieldExists('hora_fin')
            ->assertFormFieldExists('quedada_lugar')
            ->assertFormFieldExists('quedada_hora')
            ->assertFormFieldExists('quedada_url')
            ->fillForm([
                'hora_inicio' => '08:00', 'hora_fin' => '14:00',
                'quedada_lugar' => 'Bar Manolo', 'quedada_hora' => '07:00', 'quedada_url' => 'https://maps.app.goo.gl/bar',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $manga = $this->proxima->fresh();
        $this->assertSame('08:00', Manga::horaCorta($manga->hora_inicio));
        $this->assertSame('14:00', Manga::horaCorta($manga->hora_fin));
        $this->assertSame('07:00', Manga::horaCorta($manga->quedada_hora));
        $this->assertSame('Bar Manolo', $manga->quedada_lugar);
        $this->assertSame('de 08:00 a 14:00', $manga->horario());
        $this->assertSame('08:00–14:00', $manga->horarioCorto());
        $this->assertSame('a las 07:00 en Bar Manolo', $manga->quedada());

        // El enlace de la quedada tiene que ser un enlace.
        Livewire::test(EditManga::class, ['record' => $this->proxima->getRouteKey()])
            ->fillForm(['quedada_url' => 'el bar de siempre'])
            ->call('save')
            ->assertHasFormErrors(['quedada_url' => 'url']);

        // Y todo se puede vaciar.
        Livewire::test(EditManga::class, ['record' => $this->proxima->getRouteKey()])
            ->fillForm(['hora_inicio' => null, 'hora_fin' => null, 'quedada_lugar' => null, 'quedada_hora' => null, 'quedada_url' => null])
            ->call('save')
            ->assertHasNoFormErrors();
        $manga = $this->proxima->fresh();
        $this->assertNull($manga->horario());
        $this->assertNull($manga->quedada());
    }

    public function test_medio_horario_o_media_quedada_tambien_se_cuentan_bien(): void
    {
        $this->limpia();
        $this->proxima->update(['hora_inicio' => '08:00']);
        $this->assertSame('desde las 08:00', $this->proxima->fresh()->horario());
        $this->assertSame('08:00', $this->proxima->fresh()->horarioCorto());

        $this->proxima->update(['hora_inicio' => null, 'hora_fin' => '14:00']);
        $this->assertSame('hasta las 14:00', $this->proxima->fresh()->horario());
        $this->assertSame('hasta 14:00', $this->proxima->fresh()->horarioCorto());

        $this->proxima->update(['quedada_lugar' => 'Bar Manolo']);
        $this->assertSame('en Bar Manolo', $this->proxima->fresh()->quedada());

        $this->proxima->update(['quedada_lugar' => null, 'quedada_hora' => '07:00:00']); // como lo devuelve Postgres
        $this->assertSame('a las 07:00', $this->proxima->fresh()->quedada());
    }

    public function test_la_convocatoria_cuenta_el_horario_y_la_quedada(): void
    {
        $sin = Compartir::textoConvocatoria($this->limpia());
        $this->assertStringNotContainsString('Quedada', $sin);
        $this->assertStringNotContainsString('de 08:00', $sin);

        $texto = Compartir::textoConvocatoria($this->conHorarioYQuedada());
        $this->assertStringContainsString('en Embalse de Entrepeñas, de 08:00 a 14:00.', $texto);
        $this->assertStringContainsString("🤝 Quedada previa a las 07:00 en Bar Manolo\n📍 Cómo llegar a la quedada: https://maps.app.goo.gl/bar", $texto);
        // El «cómo llegar» de la quedada va después de la quedada, y el enlace de «asistiré» al final.
        $this->assertStringEndsWith($this->proxima->urlPublica(), $texto);
    }

    public function test_la_pagina_publica_de_la_manga_lo_ensena(): void
    {
        $manga = $this->conHorarioYQuedada();

        $this->get($manga->urlPublica())
            ->assertOk()
            ->assertSee('de 08:00 a 14:00')
            ->assertSee('Quedada previa a las 07:00 en Bar Manolo')
            ->assertSee('https://maps.app.goo.gl/bar');
    }

    public function test_el_inicio_del_socio_lo_ensena(): void
    {
        $this->conHorarioYQuedada();
        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('app'));

        Livewire::test(Inicio::class)
            ->assertSee('08:00–14:00')
            ->assertSee('Quedada previa a las 07:00 en Bar Manolo')
            ->assertSeeHtml('href="https://maps.app.goo.gl/bar"');
    }

    public function test_el_listado_de_mangas_del_admin_lo_ensena(): void
    {
        $this->conHorarioYQuedada();
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListMangas::class)->assertSee('08:00–14:00');
    }
}
