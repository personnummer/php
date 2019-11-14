<?php

namespace Frozzare\Personnummer;

use PHPUnit_Framework_TestCase;
use VladaHejda\AssertException;

function time()
{
    return 1565704890;
}

class PersonnummerTest extends PHPUnit_Framework_TestCase
{
    use AssertException;

    protected $invalidNumbers = [
        null,
        [],
        true,
        false,
        100101001,
        '112233-4455',
        '19112233-4455',
        '9999999999',
        '199999999999',
        '9913131315',
        '9911311232',
        '9902291237',
        '19990919_3766',
        '990919_3766',
        '199909193776',
        'Just a string',
        '990919+3776',
        '990919-3776',
        '9909193776',
    ];

    public function testPersonnummerWithControlDigit()
    {
        $this->assertTrue(Personnummer::valid(6403273813));
        $this->assertTrue(Personnummer::valid('510818-9167'));
        $this->assertTrue(Personnummer::valid('19900101-0017'));
        $this->assertTrue(Personnummer::valid('19130401+2931'));
        $this->assertTrue(Personnummer::valid('196408233234'));
        $this->assertTrue(Personnummer::valid('0001010107'));
        $this->assertTrue(Personnummer::valid('000101-0107'));
        $this->assertTrue(Personnummer::valid('101010-1010'));
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
        foreach ($this->invalidNumbers as $invalidNumber) {
            $this->assertFalse(Personnummer::valid($invalidNumber));
        }
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
        $this->assertEquals('510818-9167', Personnummer::format('19510818+9167'));
        $this->assertEquals('900101-0017', Personnummer::format('19900101-0017'));
        $this->assertEquals('130401+2931', Personnummer::format('19130401+2931'));
        $this->assertEquals('130401+2931', Personnummer::format('19130401-2931'));
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

    public function testFormatWithInvalidNumbers()
    {
        foreach ($this->invalidNumbers as $invalidNumber) {
            $this->assertFalse(Personnummer::format($invalidNumber));
        }
    }

    public function testAge()
    {
        $this->assertSame(55, Personnummer::getAge(6403273813));
        $this->assertSame(67, Personnummer::getAge('510818-9167'));
        $this->assertSame(29, Personnummer::getAge('19900101-0017'));
        $this->assertSame(106, Personnummer::getAge('19130401+2931'));
        $this->assertSame(19, Personnummer::getAge('200002296127'));
    }

    public function testAgeWithCoOrdinationNumbers()
    {
        $this->assertSame(48, Personnummer::getAge('701063-2391'));
        $this->assertSame(54, Personnummer::getAge('640883-3231'));
    }

    public function testAgeWithInvalidNumbers()
    {
        foreach ($this->invalidNumbers as $invalidNumber) {
            $this->assertEmpty(Personnummer::getAge($invalidNumber));
        }
    }

    public function testExcludeOfCoOrdinationNumbersAge()
    {
        $this->assertEmpty(Personnummer::getAge('701063-2391', false));
        $this->assertEmpty(Personnummer::getAge('640883-3231', false));
    }

    public function testSex()
    {
        $this->assertTrue(Personnummer::isMale(6403273813, false));
        $this->assertFalse(Personnummer::isFemale(6403273813, false));
        $this->assertTrue(Personnummer::isFemale('510818-9167', false));
        $this->assertFalse(Personnummer::isMale('510818-9167', false));
    }

    public function testSexWithCoOrdinationNumbers()
    {
        $this->assertTrue(Personnummer::isMale('701063-2391'));
        $this->assertFalse(Personnummer::isFemale('701063-2391'));
        $this->assertTrue(Personnummer::isFemale('640883-3223'));
        $this->assertFalse(Personnummer::isMale('640883-3223'));
    }

    public function testSexWithInvalidNumbers()
    {
        foreach ($this->invalidNumbers as $invalidNumber) {
            $this->assertException(function () use ($invalidNumber) {
                Personnummer::isMale($invalidNumber);
            }, PersonnummerException::class);
            $this->assertException(function () use ($invalidNumber) {
                Personnummer::isFemale($invalidNumber);
            }, PersonnummerException::class);
        }
    }
}
