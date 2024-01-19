<?php

namespace Personnummer\Tests;

use Jchook\AssertThrows\AssertThrows;
use Personnummer\Personnummer;
use Personnummer\PersonnummerException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InterimNumberTest extends TestCase
{
    use AssertThrows;

    private static ?array $interim = null;

    private array $options = [
        'allowInterimNumber' => true,
    ];

    private static function init(): void
    {
        if (self::$interim === null) {
            $data = json_decode(file_get_contents('https://raw.githubusercontent.com/personnummer/meta/master/testdata/interim.json'), true, 512, JSON_THROW_ON_ERROR); // phpcs:ignore
            self::$interim = array_map(
                static fn(array $p) => new PersonnummerData($p),
                $data,
            );
        }
    }

    public static function validProvider(): array
    {
        self::init();
        return array_map(
            static fn(PersonnummerData $p) => ['num' => $p],
            array_filter(self::$interim, static fn($p) => $p->valid)
        );
    }
    public static function invalidProvider(): array
    {
        self::init();
        return array_map(
            static fn(PersonnummerData $p) => ['num' => $p],
            array_filter(self::$interim, static fn($p) => !$p->valid)
        );
    }

    #[DataProvider('validProvider')]
    public function testValidateInterim(PersonnummerData $num): void
    {
        self::assertTrue(Personnummer::valid($num->longFormat, $this->options));
        self::assertTrue(Personnummer::valid($num->separatedFormat, $this->options));
    }

    #[DataProvider('invalidProvider')]
    public function testValidateInvalidInterim(PersonnummerData $num): void
    {
        self::assertFalse(Personnummer::valid($num->longFormat, $this->options));
        self::assertFalse(Personnummer::valid($num->separatedFormat, $this->options));
    }

    #[DataProvider('validProvider')]
    public function testFormatLongInterim(PersonnummerData $num): void
    {
        $p = Personnummer::parse($num->longFormat, $this->options);
        self::assertEquals($p->format(true), $num->longFormat);
        self::assertEquals($p->format(false), $num->separatedFormat);
    }

    #[DataProvider('validProvider')]
    public function testFormatShortInterim(PersonnummerData $num): void
    {
        $p = Personnummer::parse($num->separatedFormat, $this->options);
        self::assertEquals($p->format(true), $num->longFormat);
        self::assertEquals($p->format(false), $num->separatedFormat);
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidInterimThrows(PersonnummerData $num): void
    {
        $this->assertThrows(
            PersonnummerException::class,
            fn () => Personnummer::parse($num->longFormat, $this->options)
        );
        $this->assertThrows(
            PersonnummerException::class,
            fn () => Personnummer::parse($num->separatedFormat, $this->options)
        );
    }

    #[DataProvider('validProvider')]
    public function testInterimThrowsIfNotActive(PersonnummerData $num): void
    {
        $this->assertThrows(
            PersonnummerException::class,
            fn () => Personnummer::parse($num->longFormat, [
                'allowInterimNumber' => false,
            ])
        );
        $this->assertThrows(
            PersonnummerException::class,
            fn () => Personnummer::parse($num->separatedFormat, [
                'allowInterimNumber' => false,
            ])
        );
    }
}
