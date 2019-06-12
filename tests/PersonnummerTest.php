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
        $this->assertTrue(Personnummer::valid('0001010107'));
        $this->assertTrue(Personnummer::valid('000101-0107'));
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

    public function testExcludeOfCoOrdinationNumbers()
    {
        $this->assertFalse(Personnummer::valid('701063-2391', false));
        $this->assertFalse(Personnummer::valid('640883-3231', false));
    }

    public function testWrongCoOrdinationNumbers()
    {
        $this->assertFalse(Personnummer::valid('900161-0017'));
        $this->assertFalse(Personnummer::valid('640893-3231'));
    }

    public function testFormat()
    {
        $this->assertEquals('640327-3813', Personnummer::format(6403273813));
        $this->assertEquals('510818-9167', Personnummer::format('510818-9167'));
        $this->assertEquals('900101-0017', Personnummer::format('19900101-0017'));
        $this->assertEquals('130401+2931', Personnummer::format('19130401+2931'));
        $this->assertEquals('640823-3234', Personnummer::format('196408233234'));
        $this->assertEquals('000101-0107', Personnummer::format('0001010107'));
        $this->assertEquals('000101-0107', Personnummer::format('000101-0107'));
        $this->assertEquals('130401+2931', Personnummer::format('191304012931'));

        $this->assertEquals('196403273813', Personnummer::format(6403273813, true));
        $this->assertEquals('195108189167', Personnummer::format('510818-9167', true));
        $this->assertEquals('199001010017', Personnummer::format('19900101-0017', true));
        $this->assertEquals('191304012931', Personnummer::format('19130401+2931', true));
        $this->assertEquals('196408233234', Personnummer::format('196408233234', true));
        $this->assertEquals('200001010107', Personnummer::format('0001010107', true));
        $this->assertEquals('200001010107', Personnummer::format('000101-0107', true));
        $this->assertEquals('190001010107', Personnummer::format('000101+0107', true));
    }
}
