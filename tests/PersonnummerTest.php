<?php

namespace Frozzare\Tests\Personnummer;

use Frozzare\Personnummer\Personnummer;

class PersonnummerTest extends \PHPUnit_Framework_TestCase
{
    public function testPersonnummerWithControlDigit()
    {
        $this->assertTrue(Personnummer::valid(6403273813));
        $this->assertTrue(Personnummer::valid('510818-9167'));
        $this->assertTrue(Personnummer::valid('19900101-0017'));
        $this->assertTrue(Personnummer::valid('19130401+2931'));
        $this->assertTrue(Personnummer::valid('196408233234'));
    }

    public function testPersonnummerWithoutControlDigit()
    {
        $this->assertFalse(Personnummer::valid(640327381));
        $this->assertFalse(Personnummer::valid('510818-916'));
        $this->assertFalse(Personnummer::valid('19900101-001'));
        $this->assertFalse(Personnummer::valid('100101+001'));
    }

    public function testPersonnummerWithWrongTypes()
    {
        $this->assertFalse(Personnummer::valid(null));
        $this->assertFalse(Personnummer::valid(array()));
        $this->assertFalse(Personnummer::valid(true));
        $this->assertFalse(Personnummer::valid(false));
        $this->assertFalse(Personnummer::valid(100101001));
        $this->assertFalse(Personnummer::valid('112233-4455'));
        $this->assertFalse(Personnummer::valid('19112233-4455'));
        $this->assertFalse(Personnummer::valid('9999999999'));
        $this->assertFalse(Personnummer::valid('199999999999'));
        $this->assertFalse(Personnummer::valid('9913131315'));
        $this->assertFalse(Personnummer::valid('9911311232'));
        $this->assertFalse(Personnummer::valid('9902291237'));
        $this->assertFalse(Personnummer::valid('19990919_3766'));
        $this->assertFalse(Personnummer::valid('990919_3766'));
        $this->assertFalse(Personnummer::valid('199909193776'));
        $this->assertFalse(Personnummer::valid('Just a string'));
        $this->assertFalse(Personnummer::valid('990919+3776'));
        $this->assertFalse(Personnummer::valid('990919-3776'));
        $this->assertFalse(Personnummer::valid('9909193776'));
    }

    public function testCoOrdinationNumbers()
    {
        $this->assertTrue(Personnummer::valid('701063-2391'));
        $this->assertTrue(Personnummer::valid('640883-3231'));
    }

    public function testWrongCoOrdinationNumbers()
    {
        $this->assertFalse(Personnummer::valid('900161-0017'));
        $this->assertFalse(Personnummer::valid('640893-3231'));
    }
}
