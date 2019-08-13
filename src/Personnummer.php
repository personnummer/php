<?php

namespace Frozzare\Personnummer;

use DateTime;

final class Personnummer
{
    /**
     * The Luhn algorithm.
     *
     * @param  string str
     *
     * @return int
     */
    private static function luhn($str)
    {
        $v   = 0;
        $sum = 0;

        for ($i = 0; $i < strlen($str); $i ++) {
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
     * Parse a Swedish social security number and get the parts.
     *
     * @param  string $ssn
     *
     * @return array
     */
    protected static function getParts($ssn)
    {
        $reg = '/^(\d{2}){0,1}(\d{2})(\d{2})(\d{2})([\+\-\s]?)(\d{3})(\d)$/';
        preg_match($reg, $ssn, $match);

        if (!isset($match) || count($match) !== 8) {
            return array();
        }

        $century = $match[1];
        $year    = $match[2];
        $month   = $match[3];
        $day     = $match[4];
        $sep     = $match[5];
        $num     = $match[6];
        $check   = $match[7];

        if (!in_array($sep, array('-', '+'))) {
            if (empty($century) || date('Y') - intval(strval($century) . strval($year)) < 100) {
                $sep = '-';
            } else {
                $sep = '+';
            }
        }

        if (empty($century)) {
            if ($sep === '+') {
                $baseYear = date('Y', strtotime('-100 years'));
            } else {
                $baseYear = date('Y');
            }
            $century = substr(($baseYear - (($baseYear - $year) % 100)), 0, 2);
        }

        return array(
            'century' => $century,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'sep' => $sep,
            'num' => $num,
            'check' => $check
        );
    }

    /**
     * Validate a Swedish social security number.
     *
     * @param  string|int $ssn
     * @param  bool $includeCoordinationNumber
     *
     * @return bool
     */
    public static function valid($ssn, $includeCoordinationNumber = true)
    {
        if (!is_numeric($ssn) && !is_string($ssn)) {
            return false;
        }

        $ssn = strval($ssn);
        $parts = array_pad(self::getParts($ssn), 7, '');

        if (in_array('', $parts, true)) {
            return false;
        }

        list($century, $year, $month, $day, $sep, $num, $check) = array_values($parts);

        $validDate = checkdate($month, $day, strval($century) . strval($year));
        $validCoOrdinationNumber = $includeCoordinationNumber ?
            checkdate($month, intval($day) - 60, strval($century) . strval($year)) : false;

        if (!$validDate && !$validCoOrdinationNumber) {
            return false;
        }

        $valid = self::luhn($year . $month . $day . $num) === intval($check);

        return $valid;
    }

    /**
     * Format a Swedish social security number as one of the official formats,
     * A long format or a short format.
     *
     * If the input number could not be parsed a empty string will be returned.
     *
     * @param  string|int $str
     * @param  bool $longFormat YYMMDD-XXXX or YYYYMMDDXXXX since the tax office says both are official
     *
     * @return string
     */
    public static function format($ssn, $longFormat = false)
    {
        if (!self::valid($ssn)) {
            return '';
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
     * Get age from a Swedish social security number.
     *
     * @param  string|int $ssn
     * @param  bool $includeCoordinationNumber
     *
     * @return int
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
        $d2 = new DateTime(sprintf('%s%s-%s-%d', $parts['century'], $parts['year'], $parts['month'], $day));

        return $d1->diff($d2)->y;
    }
}
