<?php

namespace Tests\Unit;

use App\Models\Socio;
use PHPUnit\Framework\TestCase;

/** El teléfono como lo teclea el club, al número que entiende wa.me. */
class TelefonoWhatsAppTest extends TestCase
{
    public function test_un_movil_espanol_lleva_el_34_por_defecto(): void
    {
        $this->assertSame('34600112233', Socio::telefonoWhatsApp('600112233'));
        $this->assertSame('34600112233', Socio::telefonoWhatsApp('600 11 22 33'));
        $this->assertSame('34600112233', Socio::telefonoWhatsApp('600-11-22-33'));
        $this->assertSame('34600112233', Socio::telefonoWhatsApp('600.112.233'));
        $this->assertSame('34722334455', Socio::telefonoWhatsApp('722 334 455'));
        $this->assertSame('34911223344', Socio::telefonoWhatsApp('91 122 33 44'));
    }

    public function test_con_prefijo_se_respeta(): void
    {
        $this->assertSame('34600112233', Socio::telefonoWhatsApp('+34 600 11 22 33'));
        $this->assertSame('34600112233', Socio::telefonoWhatsApp('0034600112233'));
        $this->assertSame('351912345678', Socio::telefonoWhatsApp('+351 912 345 678'));
        $this->assertSame('447911123456', Socio::telefonoWhatsApp('+44 7911 123456'));
    }

    public function test_lo_que_no_es_un_telefono_no_cuela(): void
    {
        $this->assertNull(Socio::telefonoWhatsApp(null));
        $this->assertNull(Socio::telefonoWhatsApp(''));
        $this->assertNull(Socio::telefonoWhatsApp('paco'));
        $this->assertNull(Socio::telefonoWhatsApp('12345'));
        $this->assertNull(Socio::telefonoWhatsApp('123456789')); // 9 cifras pero no empieza por 6-9
        $this->assertNull(Socio::telefonoWhatsApp('1234567890123456')); // demasiado largo
    }
}
