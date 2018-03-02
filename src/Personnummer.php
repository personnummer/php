<?php

namespace Frozzare\Personnummer;

final class Personnummer
{
    /**
     * The Luhn algorithm.
     *
     * @param string str
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
     * Parse Swedish social security numbers and get the parts
     *
     * @param string $str
     *
     * @return array
     */
    protected static function getParts($str) {
        $reg = '/^(\d{2}){0,1}(\d{2})(\d{2})(\d{2})([\+\-\s]?)(\d{3})(\d)$/';
        preg_match($reg, $str, $match);

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
            $sep = '-';
        }

        if (empty($century)) {
            if ($sep === '+') {
                $baseYear = date('Y', strtotime('-200 years'));
            } else {
                $baseYear = date('Y', strtotime('-100 years'));
            }
            $century = substr((100 + $baseYear + ($year - $baseYear) % 100), 0, 2);
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
     * Validate Swedish social security numbers.
     *
     * @param string|int $str
     *
     * @return bool
     */
    public static function valid($str)
    {
        if (!is_numeric($str) && !is_string($str)) {
            return false;
        }

        $str = strval($str);

        $parts = array_pad(self::getParts($str), 7, '');

        if (in_array('', $parts, true)) {
            return false;
        }

        list($century, $year, $month, $day, $sep, $num, $check) = array_values($parts);

        $validDate = checkdate($month, $day, strval($century) . strval($year));
        $validCoOrdinationNumber = checkdate($month, intval($day) - 60, strval($century) . strval($year));

        if (!$validDate && !$validCoOrdinationNumber) {
            return false;
        }

        $valid = self::luhn($year . $month . $day . $num) === intval($check);

        return $valid;
    }

    /**
     * Format Swedish social security numbers to official format
     *
     * @param string|int $str
     * @param bool $longFormat YYMMDD-XXXX or YYYYMMDDXXXX since the tax office says both are official
     *
     * @return string
     */
    public static function format($str, $longFormat = false) {
        if (!self::valid($str)) {
            return '';
        }

        $parts = self::getParts($str);

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
}
