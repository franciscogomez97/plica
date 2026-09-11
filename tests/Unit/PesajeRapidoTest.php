<?php

namespace Tests\Unit;

use App\Services\PesajeRapido;
use PHPUnit\Framework\TestCase;

/** Lo que teclea el admin en la fila rápida, con sus manías, a gramos y milímetros. */
class PesajeRapidoTest extends TestCase
{
    public function test_gramos_acepta_enteros_y_separadores_de_miles_o_kilos(): void
    {
        $this->assertSame(3450, PesajeRapido::gramos('3450'));
        $this->assertSame(3450, PesajeRapido::gramos('3.450'));
        $this->assertSame(3450, PesajeRapido::gramos('3,450'));
        $this->assertSame(3450, PesajeRapido::gramos(' 3450 '));
        $this->assertSame(0, PesajeRapido::gramos(''));
        $this->assertSame(0, PesajeRapido::gramos('0'));
        $this->assertSame(12000, PesajeRapido::gramos('12.000'));
    }

    public function test_gramos_rechaza_lo_que_no_es_un_peso(): void
    {
        $this->assertNull(PesajeRapido::gramos('abc'));
        $this->assertNull(PesajeRapido::gramos('3,45'));   // ¿3,45 kg o 345 g? No se adivina.
        $this->assertNull(PesajeRapido::gramos('-100'));
        $this->assertNull(PesajeRapido::gramos('3 450'));
    }

    public function test_entero(): void
    {
        $this->assertSame(3, PesajeRapido::entero('3'));
        $this->assertSame(0, PesajeRapido::entero(''));
        $this->assertNull(PesajeRapido::entero('dos'));
        $this->assertNull(PesajeRapido::entero('1,5'));
    }

    public function test_medidas_en_cm_separadas_por_espacios_a_milimetros(): void
    {
        $this->assertSame([585, 620, 450], PesajeRapido::medidasMm('58,5 62 45'));
        $this->assertSame([585], PesajeRapido::medidasMm('58.5'));
        $this->assertSame([585, 620], PesajeRapido::medidasMm("58,5\n62"));
        $this->assertSame([585, 620], PesajeRapido::medidasMm('58,5; 62'));
        $this->assertSame([], PesajeRapido::medidasMm(''));
        $this->assertSame([], PesajeRapido::medidasMm('   '));
    }

    public function test_medidas_rechaza_lo_que_no_es_un_numero(): void
    {
        $this->assertNull(PesajeRapido::medidasMm('58,5 grande'));
        $this->assertNull(PesajeRapido::medidasMm('58,555'));
        $this->assertNull(PesajeRapido::medidasMm('-58'));
    }

    public function test_medidas_vuelven_a_texto_sin_decimales_de_mas(): void
    {
        $this->assertSame('58,5 62 45', PesajeRapido::medidasTexto([585, 620, 450]));
        $this->assertSame('', PesajeRapido::medidasTexto([]));
        $this->assertSame('120,5', PesajeRapido::medidasTexto([1205]));
    }
}
