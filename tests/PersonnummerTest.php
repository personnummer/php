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
    use AssertError;
    use AssertThrows;

    private static $testdataList;

    private static $testdataStructured;

    private static $availableListFormats = [
        'integer',
        'long_format',
        'short_format',
        'separated_format',
        'separated_long',
    ];

    private static $options = [
        'allowCoordinationNumber' => false,
        'allowTNumber' => false,
        'allowVgrReserveNumber' => false,
        'allowSllReserveNumber' => false,
        'allowRvbReserveNumber' => false,
        'allowNorwegianBirthNumber' => false,
        'allowDanishCprNumber' => false,
    ];

    public static function setUpBeforeClass(): void
    {
        self::$testdataList = json_decode(file_get_contents('./data/list.json'), true); // phpcs:ignore
        self::$testdataStructured = json_decode(file_get_contents('./data/structured.json'), true); // phpcs:ignore
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
            new Personnummer('000101-R220', [
                'allowTNumber' => false,
                'allowVgrReserveNumber' => false,
                'allowRvbReserveNumber' => false,
            ]);
        });
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('19800906K148', ['allowVgrReserveNumber' => false]);
        });
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('992004920019', ['allowSllReserveNumber' => false]);
        });
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('820202-R620', ['allowRvbReserveNumber' => false]);
        });
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('010100-1234', ['allowDanishCprNumber' => false]);
        });
        $this->assertError(function () {
            new Personnummer('1212121212', ['invalidOption' => true]);
        }, E_USER_WARNING);
    }

    public function testPersonnummerData()
    {
        foreach (self::$testdataList as $testdata) {
            foreach (self::$availableListFormats as $format) {
                // Skip integer format for reserve numbers with letters (they can't be represented as integers)
                if (
                    $format === 'integer' && $testdata['integer'] === 0 &&
                    in_array($testdata['type'], ['vgrReserveNumber', 'rvbReserveNumber'])
                ) {
                    continue;
                }

                $this->assertSame(
                    $testdata['valid'],
                    Personnummer::valid($testdata[$format], [
                        'allowCoordinationNumber' => true,
                        'allowTNumber' => false,
                        'allowVgrReserveNumber' => $testdata['type'] === 'vgrReserveNumber',
                        'allowSllReserveNumber' => $testdata['type'] === 'sllReserveNumber',
                        'allowRvbReserveNumber' => $testdata['type'] === 'rvbReserveNumber',
                        'allowDanishCprNumber' => false,
                        'allowNorwegianBirthNumber' => $testdata['type'] === 'norwegianBirthNumber',
                    ]),
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
            // Skip non-Swedish formats in this Swedish-focused test
            if (in_array($ssnType, ['danishCpr', 'norwegianBirthNumber'])) {
                continue;
            }

            foreach ($testdataInputs as $testdataType => $testdata) {
                foreach ($testdata as $valid => $ssns) {
                    foreach ($ssns as $ssn) {
                        $this->assertSame(
                            $valid === 'valid' && $ssnType === 'personalIdentityNumber',
                            Personnummer::valid($ssn, [
                                'allowCoordinationNumber' => false,
                                'allowTNumber' => false,
                                'allowVgrReserveNumber' => false,
                                'allowSllReserveNumber' => false,
                                'allowRvbReserveNumber' => false,
                                'allowDanishCprNumber' => false,
                            ]),
                            sprintf(
                                '%s should be %s',
                                $ssn,
                                ($valid === 'valid' && $ssnType === 'personalIdentityNumber' ? 'valid' : 'not valid')
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

                    // Skip integer format for reserve numbers with letters
                    if (
                        $format === 'integer' && $testdata['integer'] === 0 &&
                        in_array($testdata['type'], ['vgrReserveNumber', 'rvbReserveNumber'])
                    ) {
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
            if (! $testdata['valid']) {
                foreach (self::$availableListFormats as $format) {
                    $this->assertThrows(PersonnummerException::class, function () use ($testdata, $format) {
                        Personnummer::parse($testdata[$format], [
                            'allowCoordinationNumber' => true,
                            'allowTNumber' => false,
                            'allowVgrReserveNumber' => false,
                            'allowSllReserveNumber' => false,
                            'allowRvbReserveNumber' => false,
                            'allowDanishCprNumber' => false,
                        ]);
                    });
                    $this->assertFalse(Personnummer::valid($testdata[$format], [
                        'allowCoordinationNumber' => true,
                        'allowTNumber' => false,
                        'allowVgrReserveNumber' => false,
                        'allowSllReserveNumber' => false,
                        'allowRvbReserveNumber' => false,
                        'allowDanishCprNumber' => false,
                    ]));
                }
            }

            if ($testdata['type'] === 'coordinationNumber') {
                foreach (self::$availableListFormats as $format) {
                    $this->assertThrows(PersonnummerException::class, function () use ($testdata, $format) {
                        Personnummer::parse($testdata[$format], [
                            'allowCoordinationNumber' => false,
                            'allowTNumber' => false,
                            'allowVgrReserveNumber' => false,
                            'allowSllReserveNumber' => false,
                            'allowRvbReserveNumber' => false,
                            'allowDanishCprNumber' => false,
                        ]);
                    });
                    $this->assertFalse(Personnummer::valid($testdata[$format], [
                        'allowCoordinationNumber' => false,
                        'allowTNumber' => false,
                        'allowVgrReserveNumber' => false,
                        'allowSllReserveNumber' => false,
                        'allowRvbReserveNumber' => false,
                        'allowDanishCprNumber' => false,
                    ]));
                }
            }
        }

        for ($i = 0; $i < 2; $i++) {
            $this->assertThrows(PersonnummerException::class, function () use ($i) {
                new Personnummer(boolval($i), [
                    'allowCoordinationNumber' => true,
                    'allowTNumber' => false,
                    'allowVgrReserveNumber' => false,
                    'allowSllReserveNumber' => false,
                    'allowRvbReserveNumber' => false,
                ]);
            });

            $this->assertFalse(Personnummer::valid(boolval($i), [
                'allowCoordinationNumber' => true,
                'allowTNumber' => false,
                'allowVgrReserveNumber' => false,
                'allowSllReserveNumber' => false,
                'allowRvbReserveNumber' => false,
            ]));
        }

        foreach ([null, []] as $invalidType) {
            $this->assertThrows(TypeError::class, function () use ($invalidType) {
                new Personnummer($invalidType, [
                    'allowCoordinationNumber' => true,
                    'allowTNumber' => false,
                    'allowVgrReserveNumber' => false,
                    'allowSllReserveNumber' => false,
                    'allowRvbReserveNumber' => false,
                ]);
            });
            $this->assertThrows(TypeError::class, function () use ($invalidType) {
                Personnummer::valid($invalidType, [
                    'allowCoordinationNumber' => true,
                    'allowTNumber' => false,
                    'allowVgrReserveNumber' => false,
                    'allowSllReserveNumber' => false,
                    'allowRvbReserveNumber' => false,
                ]);
            });
        }
    }

    public function testAge()
    {
        foreach (self::$testdataList as $testdata) {
            if ($testdata['valid']) {
                if (
                    in_array(
                        $testdata['type'],
                        ['norwegianBirthNumber', 'vgrReserveNumber', 'rvbReserveNumber', 'sllReserveNumber']
                    )
                ) {
                    // These types have different internal structures or date formats
                    continue; // Skip age test for reserve numbers and Norwegian birth numbers
                } else {
                    $birthdate = substr($testdata['separated_long'], 0, 8);
                    if ($testdata['type'] === 'coordinationNumber') {
                        $birthdate = substr($birthdate, 0, 6) .
                            str_pad(intval(substr($birthdate, -2)) - 60, 2, '0', STR_PAD_LEFT);
                    }
                }

                $expected = intval((new DateTime($birthdate))->diff(new DateTime())->format('%y'));

                foreach (self::$availableListFormats as $format) {
                    if ($format === 'short_format' && strpos($testdata['separated_format'], '+') !== false) {
                        continue;
                    }

                    // Skip integer format for reserve numbers with letters
                    if (
                        $format === 'integer' && $testdata['integer'] === 0 &&
                        in_array($testdata['type'], ['vgrReserveNumber', 'rvbReserveNumber'])
                    ) {
                        continue;
                    }

                    $this->assertSame($expected, Personnummer::parse($testdata[$format])->getAge());
                }
            }
        }
    }

    public function testAgeOnBirthday()
    {
        $date = (new DateTime())->modify('-30 years midnight');
        $expected = intval($date->diff(new DateTime())->format('%y'));

        $ssn = $date->format('Ymd') . '999';

        // Access private luhn method
        $reflector = new ReflectionClass(Personnummer::class);
        $method = $reflector->getMethod('getLuhnCheckDigit');
        $method->setAccessible(true);
        $ssn .= $method->invoke(null, substr($ssn, 2));

        $this->assertSame($expected, Personnummer::parse($ssn)->getAge());
    }

    public function testSex()
    {
        foreach (self::$testdataList as $testdata) {
            if ($testdata['valid']) {
                foreach (self::$availableListFormats as $format) {
                    // Skip integer format for reserve numbers with letters
                    if (
                        $format === 'integer' && $testdata['integer'] === 0 &&
                        in_array($testdata['type'], ['vgrReserveNumber', 'rvbReserveNumber'])
                    ) {
                        continue;
                    }

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
            'century' => [0, 2],
            'year' => [2, 2],
            'fullYear' => [0, 4],
            'month' => [4, 2],
            'day' => [6, 2],
            'sep' => [8, 1],
            'num' => [9, 3],
            'check' => [12, 1],
        ];
        foreach (self::$testdataList as $testdata) {
            if ($testdata['valid']) {
                if (
                    in_array(
                        $testdata['type'],
                        ['norwegianBirthNumber', 'vgrReserveNumber', 'rvbReserveNumber', 'sllReserveNumber']
                    )
                ) {
                    // These types have different internal structures
                    continue; // Skip properties test for reserve numbers and Norwegian birth numbers
                } else {
                    foreach ($separatedLongParts as $partName => $pos) {
                        $expected = call_user_func_array(
                            'substr',
                            array_merge([$testdata['separated_long']], $pos)
                        );
                        $this->assertSame($expected, Personnummer::parse($testdata['separated_format'])->$partName);
                        $this->assertSame(
                            $expected,
                            Personnummer::parse($testdata['separated_format'])->__get($partName)
                        );
                        $this->assertTrue(isset(Personnummer::parse($testdata['separated_format'])->$partName));
                    }
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

    /**
     * T number / Reserve number tests
     */
    public function testParseTNumber()
    {
        $this->assertEquals(new Personnummer('000101-R220'), Personnummer::parse('000101-R220'));
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

    public function testReserveNumberCharacterIsInRightPlace()
    {
        // Second position, wrong:
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('000101-1R13');
        });
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('20000101-1R13');
        });

        // Third position, wrong:
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('000101-11R3');
        });
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('20000101-11R3');
        });

        // Fourth position, wrong:
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('000101-112R');
        });
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('20000101-112R');
        });
    }

    /**
     * VGR reserve number tests
     */
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
            new Personnummer('19561110-K065', [
                'allowCoordinationNumber' => false,
                'allowTNumber' => false,
                'allowVgrReserveNumber' => true,
                'allowSllReserveNumber' => false,
                'allowRvbReserveNumber' => false,
            ]);
        });

        // Wrong gender letter (male):
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('19561110-M064', array_merge(
                self::$options,
                ['allowVgrReserveNumber' => true]
            ));
        });

        // Wrong gender letter (unknown):
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('19561110-X064', [
                'allowCoordinationNumber' => false,
                'allowTNumber' => false,
                'allowVgrReserveNumber' => true,
                'allowSllReserveNumber' => false,
                'allowRvbReserveNumber' => false,
            ]);
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
            new Personnummer('20121212X804', [
                'allowCoordinationNumber' => false,
                'allowTNumber' => false,
                'allowVgrReserveNumber' => true,
                'allowSllReserveNumber' => false,
                'allowRvbReserveNumber' => false,
            ]);
        });

        // Wrong gender letter (female):
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('20121212K803', [
                'allowCoordinationNumber' => false,
                'allowTNumber' => false,
                'allowVgrReserveNumber' => true,
                'allowSllReserveNumber' => false,
                'allowRvbReserveNumber' => false,
            ]);
        });

        // Wrong gender letter (male):
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('20121212M803', [
                'allowCoordinationNumber' => false,
                'allowTNumber' => false,
                'allowVgrReserveNumber' => true,
                'allowSllReserveNumber' => false,
                'allowRvbReserveNumber' => false,
            ]);
        });
    }

    /**
     * SLL reserve number tests
     */
    public function testSllReserveNumbersForMales()
    {
        $male = new Personnummer('992004920019');
        $this->assertEquals('992004920019', $male->format());
        $this->assertTrue($male->isSllReserveNumber());

        // Wrong check digit:
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('992004920018', [
                'allowCoordinationNumber' => false,
                'allowTNumber' => false,
                'allowVgrReserveNumber' => false,
                'allowSllReserveNumber' => true,
                'allowRvbReserveNumber' => false,
            ]);
        });

        // Wrong gender:
        $this->assertFalse($male->isFemale());
        $this->assertTrue($male->isMale());
    }

    public function testSllReserveNumbersForFemales()
    {
        $female = new Personnummer('992004922486');
        $this->assertEquals('992004922486', $female->format());
        $this->assertTrue($female->isSllReserveNumber());

        // Wrong check digit:
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('992004920028', [
                'allowCoordinationNumber' => false,
                'allowTNumber' => false,
                'allowVgrReserveNumber' => false,
                'allowSllReserveNumber' => true,
                'allowRvbReserveNumber' => false,
            ]);
        });

        // Wrong gender:
        $this->assertFalse($female->isMale());
        $this->assertTrue($female->isFemale());
    }

    public function testSllAge()
    {
        $personnummer = new Personnummer('992004920019');
        $this->assertEquals(-1, $personnummer->getAge());
    }

    public function testSllReserveNumberAge()
    {
        // Future SLL number (year 2103)
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('992103920019', [
                'allowCoordinationNumber' => false,
                'allowTNumber' => false,
                'allowVgrReserveNumber' => false,
                'allowSllReserveNumber' => true,
                'allowRvbReserveNumber' => false,
            ]);
        });

        // Past SLL number
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('991865951279', [
                'allowCoordinationNumber' => false,
                'allowTNumber' => false,
                'allowVgrReserveNumber' => false,
                'allowSllReserveNumber' => true,
                'allowRvbReserveNumber' => false,
            ]);
        });

        // SLL number from 1965
        $this->assertNotThrows(PersonnummerException::class, function () {
            new Personnummer('991965950320', array_merge(
                self::$options,
                ['allowSllReserveNumber' => true]
            ));
        });
    }

    /**
     * RVB reserve number tests
     */
    public function testRvbReserveNumberForFemaleBorn20thCentury()
    {
        // This is valid female reserve number, someone born in 1982:
        $female = new Personnummer('820202-R620');
        $this->assertEquals('820202-R620', $female->format());
        $this->assertEquals('19820202R620', $female->format(true));
        $this->assertTrue($female->isRvbReserveNumber());
        $this->assertTrue($female->isFemale());
        $this->assertFalse($female->isMale());
        //$this->assertEquals(1982, date('Y') - $female->getAge());

        // Born in 19th century should have digits in, YYMMDD-R6NN or YYMMDD-R9NN:
        foreach ([1, 2, 3, 4, 5, 7, 8] as $digit) {
            $this->assertThrows(PersonnummerException::class, function () use ($digit) {
                new Personnummer("820202-R{$digit}20", [
                    'allowCoordinationNumber' => false,
                    'allowTNumber' => false,
                    'allowVgrReserveNumber' => false,
                    'allowSllReserveNumber' => false,
                    'allowRvbReserveNumber' => true,
                ]);
            });
        }

        // Females should have an even number after R:
        foreach ([21, 23, 25, 27, 29] as $digits) {
            $this->assertThrows(PersonnummerException::class, function () use ($digits) {
                new Personnummer("820202-R{$digits}0", [
                    'allowCoordinationNumber' => false,
                    'allowTNumber' => false,
                    'allowVgrReserveNumber' => false,
                    'allowSllReserveNumber' => false,
                    'allowRvbReserveNumber' => true,
                ]);
            });
        }
    }

    public function testRvbReserveNumberForMaleBorn20thCentury()
    {
        // This is valid male reserve number, someone born in 1982:
        $male = new Personnummer('820202-R630');
        $this->assertEquals('820202-R630', $male->format());
        $this->assertEquals('19820202R630', $male->format(true));
        $this->assertTrue($male->isRvbReserveNumber());
        $this->assertTrue($male->isMale());
        $this->assertFalse($male->isFemale());
        //$this->assertEquals(1982, date('Y') - $male->getAge());

        // Born in 19th century should have digits in, YYMMDD-R6NN or YYMMDD-R9NN:
        foreach ([1, 2, 3, 4, 5, 7, 8] as $digit) {
            $this->assertThrows(PersonnummerException::class, function () use ($digit) {
                new Personnummer("820202-R{$digit}30", [
                    'allowCoordinationNumber' => false,
                    'allowTNumber' => false,
                    'allowVgrReserveNumber' => false,
                    'allowSllReserveNumber' => false,
                    'allowRvbReserveNumber' => true,
                ]);
            });
        }

        // Males should have an odd number after R:
        foreach ([20, 22, 24, 26, 28] as $digits) {
            $this->assertThrows(PersonnummerException::class, function () use ($digits) {
                new Personnummer("820202-R{$digits}0", [
                    'allowCoordinationNumber' => false,
                    'allowTNumber' => false,
                    'allowVgrReserveNumber' => false,
                    'allowSllReserveNumber' => false,
                    'allowRvbReserveNumber' => true,
                ]);
            });
        }
    }

    public function testRvbReserveNumberForFemaleBorn21thCentury()
    {
        // This is valid female reserve number, someone born in 2002:
        $female = new Personnummer('020202-R220');
        $this->assertEquals('020202-R220', $female->format());
        $this->assertEquals('20020202R220', $female->format(true));
        $this->assertTrue($female->isRvbReserveNumber());
        $this->assertTrue($female->isFemale());
        $this->assertFalse($female->isMale());
        //$this->assertEquals(2002, date('Y') - $female->getAge());

        // Born in 21th century should have digits in, YYMMDD-R2NN:
        foreach ([1, 3, 4, 5, 6, 7, 8, 9] as $digit) {
            $this->assertThrows(PersonnummerException::class, function () use ($digit) {
                new Personnummer("020202-R{$digit}20", [
                    'allowCoordinationNumber' => false,
                    'allowTNumber' => false,
                    'allowVgrReserveNumber' => false,
                    'allowSllReserveNumber' => false,
                    'allowRvbReserveNumber' => true,
                ]);
            });
        }

        // Females should have an even number after R:
        foreach ([21, 23, 25, 27, 29] as $digits) {
            $this->assertThrows(PersonnummerException::class, function () use ($digits) {
                new Personnummer("820202-R{$digits}0", [
                    'allowCoordinationNumber' => false,
                    'allowTNumber' => false,
                    'allowVgrReserveNumber' => false,
                    'allowSllReserveNumber' => false,
                    'allowRvbReserveNumber' => true,
                ]);
            });
        }
    }

    public function testRvbReserveNumberForMaleBorn21thCentury()
    {
        // This is valid male reserve number, someone born in 2002:
        $male = new Personnummer('020202-R230');
        $this->assertEquals('020202-R230', $male->format());
        $this->assertEquals('20020202R230', $male->format(true));
        $this->assertTrue($male->isRvbReserveNumber());
        $this->assertTrue($male->isMale());
        $this->assertFalse($male->isFemale());
        //$this->assertEquals(2002, date('Y') - $male->getAge());

        // Born in 21th century should have digits in, YYMMDD-R2NN:
        foreach ([1, 3, 4, 5, 6, 7, 8, 9] as $digit) {
            $this->assertThrows(PersonnummerException::class, function () use ($digit) {
                new Personnummer("020202-R{$digit}30", [
                    'allowCoordinationNumber' => false,
                    'allowTNumber' => false,
                    'allowVgrReserveNumber' => false,
                    'allowSllReserveNumber' => false,
                    'allowRvbReserveNumber' => true,
                ]);
            });
        }

        // Males should have an odd number after R:
        foreach ([20, 22, 24, 26, 28] as $digits) {
            $this->assertThrows(PersonnummerException::class, function () use ($digits) {
                new Personnummer("820202-R{$digits}0", [
                    'allowCoordinationNumber' => false,
                    'allowTNumber' => false,
                    'allowVgrReserveNumber' => false,
                    'allowSllReserveNumber' => false,
                    'allowRvbReserveNumber' => true,
                ]);
            });
        }
    }

    /**
     * Norwsegian birth numbers
     */
    public function testNorwegianBirthNumbers()
    {
        // 03016213704 27034513436 20089423415
        $male = new Personnummer('03016213704');
        $this->assertEquals('03016213704', $male->format());
        $this->assertEquals('03016213704', $male->format(true));
        $this->assertTrue($male->isNorwegianBirthNumber());
        $this->assertTrue($male->isMale());
        $this->assertFalse($male->isFemale());

        $female = new Personnummer('27034513436');
        $this->assertEquals('27034513436', $female->format());
        $this->assertEquals('27034513436', $female->format(true));
        $this->assertTrue($female->isNorwegianBirthNumber());
        $this->assertTrue($female->isFemale());
        $this->assertFalse($female->isMale());
    }

    /**
     * Test Danish CPR numbers
     */
    public function testDanishCprNumbers()
    {
        // Test valid Danish CPR numbers using reasonable ages
        $cpr1990s = new Personnummer('010190-1234', ['allowDanishCprNumber' => true]);
        $this->assertTrue($cpr1990s->isDanishCprNumber());
        $this->assertEquals('010190-1234', $cpr1990s->format());
        $this->assertEquals('0101901234', $cpr1990s->format(true));
        $this->assertEquals('19', $cpr1990s->century);
        $this->assertEquals('01', $cpr1990s->day);
        $this->assertEquals('01', $cpr1990s->month);
        $this->assertEquals('90', $cpr1990s->year);
        $this->assertEquals('1990', $cpr1990s->fullYear);

        // Test 2000s century determination (7th digit = 4, year = 10)
        $cpr2000s = new Personnummer('010110-4567', ['allowDanishCprNumber' => true]);
        $this->assertTrue($cpr2000s->isDanishCprNumber());
        $this->assertEquals('20', $cpr2000s->century);
        $this->assertEquals('2010', $cpr2000s->fullYear);

        // Test gender determination
        $male = new Personnummer('010190-1235', ['allowDanishCprNumber' => true]);
        $this->assertTrue($male->isMale());
        $this->assertFalse($male->isFemale());

        $female = new Personnummer('010190-1234', ['allowDanishCprNumber' => true]);
        $this->assertTrue($female->isFemale());
        $this->assertFalse($female->isMale());

        // Test format consistency
        $this->assertEquals('010190-1234', $cpr1990s->format());
        $this->assertEquals('0101901234', $cpr1990s->format(true));
    }

    public function testDanishCprStructuredData()
    {
        foreach (self::$testdataStructured['danishCpr'] as $testdataType => $testdata) {
            foreach ($testdata as $valid => $cprs) {
                foreach ($cprs as $cpr) {
                    $this->assertSame(
                        $valid === 'valid',
                        Personnummer::valid($cpr, [
                            'allowCoordinationNumber' => false,
                            'allowTNumber' => false,
                            'allowVgrReserveNumber' => false,
                            'allowSllReserveNumber' => false,
                            'allowRvbReserveNumber' => false,
                            'allowNorwegianBirthNumber' => false,
                            'allowDanishCprNumber' => true,
                        ]),
                        sprintf(
                            'Danish CPR %s should be %s',
                            $cpr,
                            ($valid === 'valid' ? 'valid' : 'not valid')
                        )
                    );
                }
            }
        }
    }

    public function testDanishCprCenturyRules()
    {
        // Test century determination rules with realistic ages

        // Digit 0-3: 1900-1999
        $cpr1 = new Personnummer('010190-0123', ['allowDanishCprNumber' => true]);
        $this->assertEquals('19', $cpr1->century);

        // Digit 4: 2000-2099 if year 00-36
        $cpr2 = new Personnummer('010120-4123', ['allowDanishCprNumber' => true]);
        $this->assertEquals('20', $cpr2->century);

        // Digit 4: 1900-1999 if year 37-99
        $cpr3 = new Personnummer('010150-4123', ['allowDanishCprNumber' => true]);
        $this->assertEquals('19', $cpr3->century);

        // Digit 9: 2000-2099 if year 00-36 (using year 20 for reasonable age)
        $cpr4 = new Personnummer('010120-9123', ['allowDanishCprNumber' => true]);
        $this->assertEquals('20', $cpr4->century);

        // Digit 9: 1900-1999 if year 37-99
        $cpr5 = new Personnummer('010180-9123', ['allowDanishCprNumber' => true]);
        $this->assertEquals('19', $cpr5->century);
    }

    public function testDanishCprWithoutDashes()
    {
        // Test valid Danish CPR without dash
        $cprNoDash = new Personnummer('0104999995', ['allowDanishCprNumber' => true]);
        $this->assertTrue($cprNoDash->isDanishCprNumber());
        $this->assertEquals('010499-9995', $cprNoDash->format());
        $this->assertEquals('0104999995', $cprNoDash->format(true));
        $this->assertEquals('19', $cprNoDash->century);
        $this->assertTrue($cprNoDash->isMale());
        $this->assertFalse($cprNoDash->isFemale());

        // Test that invalid Danish CPR fails (using dash format to ensure Danish detection)
        $this->assertThrows(PersonnummerException::class, function () {
            new Personnummer('010203-1234', ['allowDanishCprNumber' => true]);
        });

        // Ensure same number works with dash format
        $cprWithDash = new Personnummer('010499-9995', ['allowDanishCprNumber' => true]);
        $this->assertEquals($cprNoDash->format(), $cprWithDash->format());
        $this->assertEquals($cprNoDash->century, $cprWithDash->century);
    }

    public function testAllowPersonalIdentityNumberOption()
    {
        // Test with a Swedish number that can't be interpreted as Danish CPR
        $swedishNumber = '190905271474'; // From existing test data

        // Should work with Swedish enabled (default)
        $this->assertTrue(Personnummer::valid($swedishNumber));

        // Should work with Swedish explicitly enabled
        $this->assertTrue(Personnummer::valid($swedishNumber, ['allowPersonalIdentityNumber' => true]));

        // Should fail with Swedish disabled
        $this->assertFalse(Personnummer::valid($swedishNumber, ['allowPersonalIdentityNumber' => false]));

        // Test that exception is thrown when Swedish is disabled
        $this->assertThrows(PersonnummerException::class, function () use ($swedishNumber) {
            new Personnummer($swedishNumber, ['allowPersonalIdentityNumber' => false]);
        });

        // Test with coordination number
        $coordinationNumber = '198211059954'; // Valid Swedish number from test data

        // Should work with Swedish enabled (default)
        $this->assertTrue(Personnummer::valid($coordinationNumber));

        // Should fail when Swedish is disabled
        $this->assertFalse(Personnummer::valid($coordinationNumber, ['allowPersonalIdentityNumber' => false]));

        // Test format switching: disable Swedish to force other format detection
        // Use a number that could potentially be valid in multiple formats
        $switchableNumber = '121212-1212';

        // When Swedish is disabled but Danish CPR is enabled, should still work
        $this->assertTrue(Personnummer::valid($switchableNumber, [
            'allowPersonalIdentityNumber' => false,
            'allowDanishCprNumber' => true
        ]));
    }

    public function testIsPersonalIdentityNumber()
    {
        // Test regular Swedish personal identity number
        $swedishNumber = new Personnummer('190905271474');
        $this->assertTrue($swedishNumber->isPersonalIdentityNumber());
        $this->assertFalse($swedishNumber->isDanishCprNumber());
        $this->assertFalse($swedishNumber->isCoordinationNumber());
        $this->assertFalse($swedishNumber->isReserveNumber());

        // Test coordination number (should also be considered personal identity number)
        $coordinationNumber = new Personnummer('570574-9959');
        $this->assertTrue($coordinationNumber->isPersonalIdentityNumber());
        $this->assertTrue($coordinationNumber->isCoordinationNumber()); // Also a coordination number

        // Test Danish CPR (should NOT be personal identity number)
        $danishCpr = new Personnummer('010499-9995', ['allowDanishCprNumber' => true]);
        $this->assertFalse($danishCpr->isPersonalIdentityNumber());
        $this->assertTrue($danishCpr->isDanishCprNumber());

        // Test with allowPersonalIdentityNumber disabled
        $swedishDisabled = new Personnummer('121212-1212', [
            'allowPersonalIdentityNumber' => false,
            'allowDanishCprNumber' => true
        ]);
        $this->assertFalse($swedishDisabled->isPersonalIdentityNumber());
        $this->assertTrue($swedishDisabled->isDanishCprNumber()); // Should be processed as Danish instead

        // Test various number types from test data
        foreach (self::$testdataList as $testdata) {
            if ($testdata['valid'] && $testdata['type'] === 'personalIdentityNumber') {
                $p = new Personnummer($testdata['separated_format']);
                $this->assertTrue(
                    $p->isPersonalIdentityNumber(),
                    "SSN {$testdata['separated_format']} should be personal identity number"
                );
            }

            if ($testdata['valid'] && $testdata['type'] === 'coordinationNumber') {
                $p = new Personnummer($testdata['separated_format']);
                $this->assertTrue(
                    $p->isPersonalIdentityNumber(),
                    "Coordination number {$testdata['separated_format']} should be personal identity number"
                );
                $this->assertTrue($p->isCoordinationNumber());
            }
        }
    }
}
