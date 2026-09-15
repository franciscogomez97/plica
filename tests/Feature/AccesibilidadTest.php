<?php

namespace Tests\Feature;

use App\Models\Manga;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que dejó la auditoría de accesibilidad del 15 de septiembre de 2026, para
 * que no vuelva a bajar sin que salte: contraste del verde de marca (AA: 4,5:1
 * en texto normal), objetivos táctiles, orden de tabulación y landmarks.
 */
class AccesibilidadTest extends TestCase
{
    use RefreshDatabase;

    /** Contraste WCAG entre dos colores hex (#rrggbb). */
    private static function contraste(string $a, string $b): float
    {
        $lum = function (string $hex): float {
            $rgb = array_map(fn (string $h) => hexdec($h) / 255, str_split(ltrim($hex, '#'), 2));
            $lin = array_map(fn (float $c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $rgb);

            return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
        };
        [$l1, $l2] = [$lum($a), $lum($b)];

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    public function test_el_verde_de_marca_sobre_blanco_pasa_aa_y_el_de_whatsapp_lleva_texto_oscuro(): void
    {
        // Tailwind: emerald-600 #059669 (3,77:1, no pasa) y emerald-700 #047857 (5,5:1, pasa).
        $this->assertLessThan(4.5, self::contraste('#059669', '#ffffff'), 'el 600 no pasa: por eso no se usa como texto');
        $this->assertGreaterThanOrEqual(4.5, self::contraste('#047857', '#ffffff'));
        $this->assertGreaterThanOrEqual(4.5, self::contraste('#ffffff', '#047857'), 'texto blanco sobre botón emerald-700');

        // Botón de WhatsApp: su verde con texto blanco da 1,9:1; con slate-900, más de 9:1.
        $this->assertLessThan(4.5, self::contraste('#25d366', '#ffffff'));
        $this->assertGreaterThanOrEqual(4.5, self::contraste('#25d366', '#0f172a'));
        $this->assertStringContainsString('background:#25d366; color:#0f172a', file_get_contents(resource_path('views/partials/compartir.blade.php')));
    }

    public function test_las_vistas_no_usan_el_emerald_600_como_texto_ni_como_fondo_de_boton(): void
    {
        $usos = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $fichero) {
            if (! $fichero->isFile() || ! str_ends_with($fichero->getFilename(), '.blade.php')) {
                continue;
            }
            if (preg_match_all('/\b(?:text|bg)-emerald-600\b/', (string) file_get_contents($fichero->getPathname()), $m)) {
                $usos[] = str_replace(resource_path('views').'/', '', $fichero->getPathname()).' ×'.count($m[0]);
            }
        }
        $this->assertSame([], $usos, 'texto y botones de marca van en emerald-700 (AA)');
    }

    public function test_los_paneles_usan_el_700_para_botones_y_enlaces(): void
    {
        foreach (['admin', 'app'] as $panel) {
            $primary = Filament::getPanel($panel)->getColors()['primary'];
            $this->assertSame(Color::Emerald[700], $primary[600], "panel $panel: el tono 600 (botones) es el emerald-700");
            $this->assertSame(Color::Emerald[600], $primary[500], "panel $panel: el tono 500 es el emerald-600");
        }
    }

    public function test_el_boton_de_quitar_del_pesaje_no_entra_en_el_orden_de_tabulacion(): void
    {
        $this->assertStringContainsString('class="pesaje-quitar" tabindex="-1"', file_get_contents(resource_path('views/filament/mangas/pesaje.blade.php')));
    }

    public function test_las_paginas_publicas_tienen_landmarks_con_nombre_y_enlaces_del_pie_tocables(): void
    {
        $this->seed(DemoSeeder::class);
        $this->get('/c/cd-pesca-piloto')->assertOk()
            ->assertSee('<nav class="flex items-center gap-4 text-sm" aria-label="Principal">', escape: false)
            ->assertSee('aria-label="Legal"', escape: false)
            ->assertSee('class="inline-flex min-h-11 items-center hover:text-emerald-800">Aviso legal', escape: false);

        // Las cabeceras de manga del cuadro (enlaces) tienen relleno: objetivo táctil de 24 px como mínimo.
        $this->assertStringContainsString('.pc thead th a { display: inline-block; padding: .35rem 0; min-height: 1.5rem; }', file_get_contents(resource_path('views/public/partials/cuadro.blade.php')));
    }

    public function test_en_la_lista_de_mangas_la_seccion_se_ve_tambien_en_el_movil(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $manga = Manga::whereNotNull('seccion_id')->with('seccion')->firstOrFail();
        // La columna «solo móvil» (debajo del nombre) existe además de la insignia de escritorio.
        \Livewire\Livewire::test(\App\Filament\Resources\Mangas\Pages\ListMangas::class)
            ->assertTableColumnExists('seccion_movil')
            ->assertTableColumnExists('seccion.nombre')
            ->assertSee($manga->seccion->nombre);
    }
}
