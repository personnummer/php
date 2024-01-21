<?php

namespace Personnummer;

use Exception;

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
    /**
     * Parse a string representation of a Swedish social security number.
     *
     * @param string $ssn Social Security number to parse.
     * @param array  $options Parse options.
     * @return PersonnummerInterface
     * @throws PersonnummerException
     */
    public static function parse(string $ssn, array $options = []): self;

    /**
     * Test a string representation of a Swedish social security number to see
     * if it's valid.
     *
     * @param string $ssn     Social Security number to parse.
     * @param array  $options Parse options.
     * @return bool
     */
    public static function valid(string $ssn, array $options = []): bool;

    /**
     * Format a Swedish social security/coordination number as one of the official formats,
     * A long format or a short format.
     *
     * If the input number could not be parsed an empty string will be returned.
     *
     * @param bool $longFormat short format YYMMDD-XXXX or long YYYYMMDDXXXX since the tax office says both are official
     * @return string
     */
    public function format(bool $longFormat = false): string;

    /**
     * Check if a Swedish social security number is for a female.
     *
     * @return bool
     */
    public function isFemale(): bool;

    /**
     * Check if a Swedish social security number is for a male.
     *
     * @return bool
     */
    public function isMale(): bool;

    /**
     * Check if the Swedish social security number is a coordination number.
     *
     * @return bool
     */
    public function isCoordinationNumber(): bool;

    /**
     * Check if the Swedish social security number is an interim number.
     *
     * @return bool
     */
    public function isInterimNumber(): bool;

    public function __construct(string $ssn, array $options = []);

    /**
     * Get age from a Swedish social security/coordination number.
     *
     * @return int
     *
     * @throws Exception When date is invalid or problems with DateTime library
     */
    public function getAge(): int;
}
