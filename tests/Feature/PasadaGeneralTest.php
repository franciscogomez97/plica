<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pasada general de septiembre de 2026: páginas de error propias, cabeceras y detalles que se veían dobles. */
class PasadaGeneralTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_paginas_de_error_son_de_plica_y_en_castellano(): void
    {
        $this->get('/esto-no-existe')->assertNotFound()
            ->assertSee('Esta página no existe')
            ->assertSee('Ir a la portada')
            ->assertSee('Plica');

        $this->seed(DemoSeeder::class);
        $this->get('/c/club-que-no-existe')->assertNotFound()->assertSee('Esta página no existe');
    }

    public function test_todas_las_respuestas_llevan_cabeceras_de_seguridad(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (['/', '/c/cd-pesca-piloto', '/app/login', '/aviso-legal'] as $url) {
            $this->get($url)->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_la_pieza_mayor_de_la_temporada_sale_una_sola_vez_en_la_seccion_publica(): void
    {
        $this->seed(DemoSeeder::class);

        $html = $this->get('/c/cd-pesca-piloto/orilla')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Pieza mayor de la temporada:'));
    }
}
