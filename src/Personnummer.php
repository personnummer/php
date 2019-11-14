<?php

namespace Frozzare\Personnummer;

use DateTime;
use Exception;

final class Personnummer
{
    /**
     * Validate a Swedish social security/coordination number.
     *
     * @param string|int $ssn                       Social security number to validate.
     * @param bool       $includeCoordinationNumber If to include Coordination numbers as valid numbers.
     *
     * @return bool
     */
    public static function valid($ssn, $includeCoordinationNumber = true)
    {
        if (!is_numeric($ssn) && !is_string($ssn)) {
            return false;
        }

        $ssn = strval($ssn);
        try {
            $parts = self::getParts($ssn);
        } catch (PersonnummerException $exception) {
            return false;
        }

        $parts = array_pad($parts, 7, '');

        if (in_array('', $parts, true)) {
            return false;
        }

        list($century, $year, $month, $day, $sep, $num, $check) = array_values($parts);

        $validDate               = checkdate($month, $day, strval($century) . strval($year));
        $validCoOrdinationNumber = $includeCoordinationNumber ?
            checkdate($month, intval($day) - 60, strval($century) . strval($year)) : false;

        if (!$validDate && !$validCoOrdinationNumber) {
            return false;
        }

        $valid = self::luhn($year . $month . $day . $num) === intval($check);

        return $valid;
    }

    /**
     * Format a Swedish social security/coordination number as one of the official formats,
     * A long format or a short format.
     *
     * If the input number could not be parsed an empty string will be returned.
     *
     * @param string|int $ssn        Social Security or Coordination number to format.
     * @param bool       $longFormat YYMMDD-XXXX or YYYYMMDDXXXX since the tax office says both are official
     *
     * @return string
     * @throws PersonnummerException On parse failure.
     */
    public static function format($ssn, $longFormat = false)
    {
        if (!self::valid($ssn)) {
            return false;
        }

        $parts = self::getParts($ssn);

        if ($longFormat) {
            $format = '%1$s%2$s%3$s%4$s%6$s%7$s';
        } else {
            $format = '%2$s%3$s%4$s%5$s%6$s%7$s';
        }

        $return = sprintf(
            $format,
            $parts['century'],
            $parts['year'],
            $parts['month'],
            $parts['day'],
            $parts['sep'],
            $parts['num'],
            $parts['check']
        );

        return $return;
    }

    /**
     * Get age from a Swedish social security/coordination number.
     *
     * @param string|int $ssn                       Social security/coordination number to get age from.
     * @param bool       $includeCoordinationNumber If to include coordination numbers in
     *
     * @return int
     * @throws PersonnummerException On parse failure.
     */
    public static function getAge($ssn, $includeCoordinationNumber = true)
    {
        if (!self::valid($ssn, $includeCoordinationNumber)) {
            return 0;
        }

        $parts = self::getParts($ssn);

        $day = intval($parts['day']);
        if ($includeCoordinationNumber && $day >= 61 && $day <= 91) {
            $day -= 60;
        }

        $ts = time();
        $d1 = new DateTime("@$ts");

        try {
            $d2 = new DateTime(sprintf('%s%s-%s-%d', $parts['century'], $parts['year'], $parts['month'], $day));
        } catch (Exception $e) {
            return 0;
        }

        return $d1->diff($d2)->y;
    }

    /**
     * Check if a Swedish social security number is for a male.
     *
     * @param string|int $ssn                       Social security number to test.
     * @param bool       $includeCoordinationNumber If to include Coordination numbers as valid numbers.
     *
     * @return bool
     * @throws PersonnummerException On parse failure.
     */
    public static function isMale($ssn, $includeCoordinationNumber = true)
    {
        if (!self::valid($ssn, $includeCoordinationNumber)) {
            throw new PersonnummerException();
        }

        $parts       = self::getParts($ssn);
        $genderDigit = substr($parts['num'], -1);

        return boolval($genderDigit % 2);
    }

    /**
     * Check if a Swedish social security number is for a female.
     *
     * @param string|int $ssn                       Social security number to test.
     * @param bool       $includeCoordinationNumber If to include Coordination numbers as valid numbers.
     *
     * @return bool
     * @throws PersonnummerException On parse failure.
     */
    public static function isFemale($ssn, $includeCoordinationNumber = true)
    {
        return !static::isMale($ssn, $includeCoordinationNumber);
    }

    /**
     * Parse a Swedish social security number and get the parts.
     *
     * @param string $ssn Social security number to get parts from.
     *
     * @return array
     * @throws PersonnummerException On parse failure.
     */
    protected static function getParts($ssn)
    {
        $reg = '/^(\d{2}){0,1}(\d{2})(\d{2})(\d{2})([\+\-\s]?)(\d{3})(\d)$/';
        preg_match($reg, $ssn, $match);

        if (!isset($match) || count($match) !== 8) {
            throw new PersonnummerException();
        }

        $century = $match[1];
        $year    = $match[2];
        $month   = $match[3];
        $day     = $match[4];
        $sep     = $match[5];
        $num     = $match[6];
        $check   = $match[7];

        if (!empty($century)) {
            if (date('Y') - intval(strval($century) . strval($year)) < 100) {
                $sep = '-';
            } else {
                $sep = '+';
            }
        } else {
            if ($sep === '+') {
                $baseYear = date('Y', strtotime('-100 years'));
            } else {
                $sep      = '-';
                $baseYear = date('Y');
            }
            $century = substr(($baseYear - (($baseYear - $year) % 100)), 0, 2);
        }

        return [
            'century' => $century,
            'year'    => $year,
            'month'   => $month,
            'day'     => $day,
            'sep'     => $sep,
            'num'     => $num,
            'check'   => $check,
        ];
    }

    /**
     * The Luhn algorithm.
     *
     * @param string $str String to run the Luhn algorithm on.
     *
     * @return int
     */
    private static function luhn($str)
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
}
