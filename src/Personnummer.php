<?php

namespace Personnummer;

use DateTime;
use Exception;

/**
 * Class Personnummer
 *
 * @package       Personnummer
 *
 * @property-read string $century
 * @property-read string $year
 * @property-read string $fullYear
 * @property-read string $month
 * @property-read string $day
 * @property-read string $sep
 * @property-read string $num
 * @property-read string $check
 */
final class Personnummer implements PersonnummerInterface
{
    private $parts;

    private $options;

    private $reserveNumberCharacter;

    /**
     *
     * @param string $ssn
     * @param array  $options
     *
     * @return PersonnummerInterface
     *
     * @throws PersonnummerException
     */
    public static function parse(string $ssn, array $options = []): PersonnummerInterface
    {
        return new self($ssn, $options);
    }

    /**
     * Check if a Swedish social security number is for a male.
     *
     * @return bool
     */
    public function isMale(): bool
    {
        $parts       = $this->parts;
        $genderDigit = substr($parts['num'], -1);

        return boolval($genderDigit % 2);
    }

    /**
     * Check if a Swedish social security number is for a female.
     *
     * @return bool
     */
    public function isFemale(): bool
    {
        return !$this->isMale();
    }

    /**
     * Format a Swedish social security/coordination number as one of the official formats,
     * A long format or a short format.
     *
     * If the input number could not be parsed an empty string will be returned.
     *
     * @param bool $longFormat short format YYMMDD-XXXX or long YYYYMMDDXXXX since the tax office says both are official
     *
     * @return string
     */
    public function format(bool $longFormat = false): string
    {
        $parts = $this->parts;

        if ($longFormat) {
            $format = '%1$s%2$s%3$s%4$s%6$s%7$s';
        } else {
            $format = '%2$s%3$s%4$s%5$s%6$s%7$s';
        }

        return sprintf(
            $format,
            $parts['century'],
            $parts['year'],
            $parts['month'],
            $parts['day'],
            $parts['sep'],
            $parts['num'],
            $parts['check']
        );
    }

    public function isCoordinationNumber(): bool
    {
        $parts = $this->parts;

        return checkdate(intval($parts['month']), $parts['day'] - 60, $parts['fullYear']);
    }

    public static function valid(string $ssn, array $options = []): bool
    {
        try {
            return self::parse($ssn, $options)->isValid();
        } catch (PersonnummerException $exception) {
            return false;
        }
    }

    /**
     * Parse a Swedish social security number and get the parts.
     *
     * @param string $ssn Social security number to get parts from.
     *
     * @return array
     * @throws PersonnummerException On parse failure.
     */
    private static function getParts(string $ssn): array
    {
        // phpcs:ignore
        $reg = '/^(?\'century\'\d{2}){0,1}(?\'year\'\d{2})(?\'month\'\d{2})(?\'day\'\d{2})(?\'sep\'[\+\-\s]?)(?\'num\'(?!000)\d{3})(?\'check\'\d)$/';
        preg_match($reg, $ssn, $match);

        if (empty($match)) {
            throw new PersonnummerException();
        }

        // Remove numeric matches
        $parts = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);

        if (!empty($parts['century'])) {
            if (date('Y') - intval(strval($parts['century']) . strval($parts['year'])) < 100) {
                $parts['sep'] = '-';
            } else {
                $parts['sep'] = '+';
            }
        } else {
            if ($parts['sep'] === '+') {
                $baseYear = date('Y', strtotime('-100 years'));
            } else {
                $parts['sep'] = '-';
                $baseYear     = date('Y');
            }
            $parts['century'] = substr(($baseYear - (($baseYear - $parts['year']) % 100)), 0, 2);
        }

        $parts['fullYear'] = $parts['century'] . $parts['year'];

        return $parts;
    }

    /**
     * The Luhn algorithm.
     *
     * @param string $str String to run the Luhn algorithm on.
     *
     * @return int
     */
    private static function luhn(string $str): int
    {
        $sum = 0;

        for ($i = 0; $i < strlen($str); $i++) {
            $v = intval($str[$i]);
            $v *= 2 - ($i % 2);

            if ($v > 9) {
                $v -= 9;
            }

            $sum += $v;
        }

        return intval(ceil($sum / 10) * 10 - $sum);
    }

    /**
     * Personnummer constructor.
     *
     * @param string $ssn
     * @param array  $options
     *
     * @throws PersonnummerException When $ssn is unparsable or invalid
     */
    public function __construct(string $ssn, array $options = [])
    {
        $this->options = $this->parseOptions($options);
        $this->parts   = self::getParts($ssn);

        if (!$this->isValid()) {
            throw new PersonnummerException();
        }
    }

    public function isReserveNumber(): bool
    {
        return $this->reserveNumberCharacter !== null;
    }

    /**
     * Get age from a Swedish social security/coordination number.
     *
     * @return int
     *
     * @throws Exception When date is invalid or problems with DateTime library
     */
    public function getAge(): int
    {
        $parts = $this->parts;

        $day = intval($parts['day']);
        if ($this->isCoordinationNumber()) {
            $day -= 60;
        }

        $birthday = new DateTime(sprintf('%s%s-%s-%d', $parts['century'], $parts['year'], $parts['month'], $day));

        return (new DateTime())->diff($birthday)->y;
    }

    public function __get(string $name)
    {
        if (isset($this->parts[$name])) {
            return $this->parts[$name];
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        trigger_error(
            sprintf(
                'Undefined property via __get(): %s in %s on line %d',
                $name,
                $trace[0]['file'],
                $trace[0]['line']
            ),
            E_USER_NOTICE
        );

        return null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->parts);
    }

    /**
     * Validate a Swedish social security/coordination number.
     *
     * @return bool
     */
    private function isValid(): bool
    {
        $parts = $this->parts;

        if (! $this->options['allowReserveNumber'] && $this->isReserveNumber()) {
            return false;
        }

        if ($this->options['allowCoordinationNumber'] && $this->isCoordinationNumber()) {
            $validDate = true;
        } else {
            $validDate = checkdate($parts['month'], $parts['day'], $parts['century'] . $parts['year']);
        }

        $checkStr   = $parts['year'] . $parts['month'] . $parts['day'] . $parts['num'];
        $validCheck = self::luhn($checkStr) === intval($parts['check']);

        return $validDate && $validCheck;
    }

    private function parseOptions(array $options): array
    {
        $defaultOptions = [
            'allowCoordinationNumber' => true,
            'allowReserveNumber' => true,
        ];

        if ($unknownKeys = array_diff_key($options, $defaultOptions)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            trigger_error(
                sprintf(
                    'Undefined option: %s in %s on line %d',
                    reset($unknownKeys),
                    $trace[0]['file'],
                    $trace[0]['line']
                ),
                E_USER_WARNING
            );
        }

        return array_merge($defaultOptions, array_intersect_key($options, $defaultOptions));
    }
}
