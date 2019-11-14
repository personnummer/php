<?php

namespace Personnummer;

/**
 * Interface PersonnummerInterface
 *
 * @property-read string $century
 * @property-read string $year
 * @property-read string $fullYear
 * @property-read string $month
 * @property-read string $day
 * @property-read string $sep
 * @property-read string $num
 * @property-read string $check
 *
 * @property-read int    $age
 */
interface PersonnummerInterface
{
    public static function parse(string $ssn, array $options = []): self;

    public static function valid(string $ssn, array $options = []): bool;

    public function format(bool $longFormat = false): string;

    public function isFemale(): bool;

    public function isMale(): bool;

    public function isCoordinationNumber(): bool;

    public function __construct(string $ssn, array $options = []);
}
