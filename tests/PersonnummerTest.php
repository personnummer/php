<?php

namespace Personnummer\Tests;

use DateTime;
use Jchook\AssertThrows\AssertThrows;
use Personnummer\Personnummer;
use Personnummer\PersonnummerException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TypeError;

class PersonnummerTest extends TestCase
{
    use AssertThrows;
    use AssertError;

    private static $testdataList;

    private static $testdataStructured;

    private static $availableListFormats = [
        'integer',
        'long_format',
        'short_format',
        'separated_format',
        'separated_long',
    ];

    public static function setUpBeforeClass(): void
    {
        self::$testdataList       = json_decode(file_get_contents('https://raw.githubusercontent.com/personnummer/meta/master/testdata/list.json'), true); // phpcs:ignore
        self::$testdataStructured = json_decode(file_get_contents('https://raw.githubusercontent.com/personnummer/meta/master/testdata/structured.json'), true); // phpcs:ignore
    }

    public function testParse()
    {
        $this->assertSame(Personnummer::class, get_class(Personnummer::parse('1212121212')));
        $this->assertEquals(new Personnummer('1212121212'), Personnummer::parse('1212121212'));
    }

    public function testOptions()
    {
        new Personnummer('1212621211');

        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('1212621211', ['allowCoordinationNumber' => false]);
        });
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('000101-R220', ['allowReserveNumber' => false]);
        });
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('19800906K148', ['allowVgrReserveNumber' => false]);
        });
        $this->assertError(function () {
            new Personnummer('1212121212', ['invalidOption' => true]);
        }, E_USER_WARNING);
    }

    public function testReserveNumber()
    {
        // Valid reserve numbers, where R/T gets replaced by a digit 1:
        $longNumber = new Personnummer('20000101-R220');
        $shortNumber = new Personnummer('000101-R220');
        $differentLetterNumber = new Personnummer('000101-T220');

        $this->assertEquals('000101-R220', $longNumber->format());
        $this->assertEquals('000101-R220', $shortNumber->format());
        $this->assertEquals('000101-T220', $differentLetterNumber->format());
    }

    public function testVgrReserveNumbersForFemales()
    {
        $female = new Personnummer('19561110-K064');
        $this->assertEquals('561110-K064', $female->format());
        $this->assertEquals('19561110K064', $female->format(true));
        $this->assertTrue($female->isVgrReserveNumber());

        // Replacing K with '5' should produce the same check digit, but not identify as VGR:
        $personnummer = new Personnummer('19561110-5064');
        $this->assertFalse($personnummer->isVgrReserveNumber());

        // Wrong check digit:
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('19561110-K065');
        });

        // Wrong gender letter (male):
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('19561110-M064');
        });

        // Wrong gender letter (unknown):
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('19561110-X064');
        });
    }

    public function testVgrReserveNumbersForMales()
    {
        $male = new Personnummer('20121212M714');
        $this->assertEquals('121212-M714', $male->format());
        $this->assertEquals('20121212M714', $male->format(true));
        $this->assertTrue($male->isVgrReserveNumber());

        // Wrong check digit:
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('20121212M713');
        });

        // Wrong gender letter (female):
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('20121212K714');
        });

        // Wrong gender letter (unknown):
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('20121212X714');
        });
    }

    public function testVgrReserveNumbersForUnknownGender()
    {
        $unknown = new Personnummer('20121212X803');
        $this->assertEquals('121212-X803', $unknown->format());
        $this->assertEquals('20121212X803', $unknown->format(true));
        $this->assertTrue($unknown->isVgrReserveNumber());

        // Wrong check digit:
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('20121212X804');
        });

        // Wrong gender letter (female):
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('20121212K803');
        });

        // Wrong gender letter (male):
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('20121212M803');
        });
    }

    public function testParseReserveNumber()
    {
        $this->assertEquals(new Personnummer('000101-R220'), Personnummer::parse('000101-R220'));
    }

    public function testPersonnummerData()
    {
        foreach (self::$testdataList as $testdata) {
            foreach (self::$availableListFormats as $format) {
                $this->assertSame(
                    $testdata['valid'],
                    Personnummer::valid($testdata[$format]),
                    sprintf(
                        '%s (%s) should be %s',
                        $testdata[$format],
                        $format,
                        $testdata['valid'] ? 'valid' : 'not valid'
                    )
                );
            }
        }

        foreach (self::$testdataStructured as $ssnType => $testdataInputs) {
            foreach ($testdataInputs as $testdataType => $testdata) {
                foreach ($testdata as $valid => $ssns) {
                    foreach ($ssns as $ssn) {
                        $this->assertSame(
                            $valid === 'valid' && $ssnType === 'ssn',
                            Personnummer::valid($ssn, ['allowCoordinationNumber' => false]),
                            sprintf(
                                '%s should be %s',
                                $ssn,
                                ($valid === 'valid' && $ssnType === 'ssn' ? 'valid' : 'not valid')
                            )
                        );
                    }
                }
            }
        }
    }

    public function testFormat()
    {
        foreach (self::$testdataList as $testdata) {
            if ($testdata['valid']) {
                foreach (self::$availableListFormats as $format) {
                    if ($format === 'short_format' && strpos($testdata['separated_format'], '+') !== false) {
                        continue;
                    }

                    $this->assertSame($testdata['separated_format'], (new Personnummer($testdata[$format]))->format());

                    $this->assertSame($testdata['long_format'], Personnummer::parse($testdata[$format])->format(true));
                }
            }
        }
    }

    public function testThrowsErrorOnInvalid()
    {
        foreach (self::$testdataList as $testdata) {
            if (!$testdata['valid']) {
                foreach (self::$availableListFormats as $format) {
                    $this->assertThrows(PersonnummerException::class, function () use ($testdata, $format) {
                        Personnummer::parse($testdata[$format]);
                    });
                    $this->assertFalse(Personnummer::valid($testdata[$format]));
                }
            }

            if ($testdata['type'] === 'con') {
                foreach (self::$availableListFormats as $format) {
                    $this->assertThrows(PersonnummerException::class, function () use ($testdata, $format) {
                        Personnummer::parse($testdata[$format], ['allowCoordinationNumber' => false]);
                    });
                    $this->assertFalse(Personnummer::valid($testdata[$format], ['allowCoordinationNumber' => false]));
                }
            }
        }

        for ($i = 0; $i < 2; $i++) {
            $this->assertThrows(PersonnummerException::class, function () use ($i) {
                new Personnummer(boolval($i));
            });

            $this->assertFalse(Personnummer::valid(boolval($i)));
        }

        foreach ([null, []] as $invalidType) {
            $this->assertThrows(TypeError::class, function () use ($invalidType) {
                new Personnummer($invalidType);
            });
            $this->assertThrows(TypeError::class, function () use ($invalidType) {
                Personnummer::valid($invalidType);
            });
        }
    }

    public function testAge()
    {
        foreach (self::$testdataList as $testdata) {
            if ($testdata['valid']) {
                $birthdate = substr($testdata['separated_long'], 0, 8);
                if ($testdata['type'] === 'con') {
                    $birthdate = substr($birthdate, 0, 6) .
                        str_pad(intval(substr($birthdate, -2)) - 60, 2, "0", STR_PAD_LEFT);
                }

                $expected = intval((new DateTime($birthdate))->diff(new DateTime())->format('%y'));

                foreach (self::$availableListFormats as $format) {
                    if ($format === 'short_format' && strpos($testdata['separated_format'], '+') !== false) {
                        continue;
                    }

                    $this->assertSame($expected, Personnummer::parse($testdata[$format])->getAge());
                }
            }
        }
    }

    public function testAgeOnBirthday()
    {
        $date     = (new DateTime())->modify('-30 years midnight');
        $expected = intval($date->diff(new DateTime())->format('%y'));

        $ssn = $date->format('Ymd') . '999';

        // Access private luhn method
        $reflector = new ReflectionClass(Personnummer::class);
        $method    = $reflector->getMethod('luhn');
        $method->setAccessible(true);
        $ssn .= $method->invoke(null, substr($ssn, 2));

        $this->assertSame($expected, Personnummer::parse($ssn)->getAge());
    }

    public function testSex()
    {
        foreach (self::$testdataList as $testdata) {
            if ($testdata['valid']) {
                foreach (self::$availableListFormats as $format) {
                    $this->assertSame($testdata['isMale'], Personnummer::parse($testdata[$format])->isMale());
                    $this->assertSame($testdata['isFemale'], Personnummer::parse($testdata[$format])->isFemale());
                }
            }
        }
    }

    public function testProperties()
    {
        // Parts, as position and length
        $separatedLongParts = [
            'century'  => [0, 2],
            'year'     => [2, 2],
            'fullYear' => [0, 4],
            'month'    => [4, 2],
            'day'      => [6, 2],
            'sep'      => [8, 1],
            'num'      => [9, 3],
            'check'    => [12, 1],
        ];
        foreach (self::$testdataList as $testdata) {
            if ($testdata['valid']) {
                foreach ($separatedLongParts as $partName => $pos) {
                    $expected = call_user_func_array('substr', array_merge([$testdata['separated_long']], $pos));
                    $this->assertSame($expected, Personnummer::parse($testdata['separated_format'])->$partName);
                    $this->assertSame($expected, Personnummer::parse($testdata['separated_format'])->__get($partName));
                    $this->assertTrue(isset(Personnummer::parse($testdata['separated_format'])->$partName));
                }
            }
        }
    }

    public function testMissingProperties()
    {
        $this->assertError(function () {
            Personnummer::parse('1212121212')->missingProperty;
        }, E_USER_NOTICE);
        $this->assertFalse(isset(Personnummer::parse('121212-1212')->missingProperty));
    }
}
