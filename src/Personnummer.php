<?php

namespace Frozzare\Personnummer;

use Datetime;
use Exception;

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
            $v *= 2 - ( $i % 2 );

            if ($v > 9) {
                $v -= 9;
            }

            $sum += $v;
        }

        return intval(ceil($sum / 10) * 10 - $sum);
    }

    /**
     * Test date if luhn is true.
     *
     * @param string|int $year
     * @param string|int $month
     * @param string|int $day
     *
     * @return bool
     */
    private static function testDate($year, $month, $day)
    {
        try {
            date_default_timezone_set('Europe/Stockholm');
            $date = new DateTime($year . '-' . $month . '-' . $day);

            if (strlen($month) < 2) {
                $month = '0' . $month;
            }

            if (strlen($day) < 2) {
                $day = '0' . $day;
            }

            return !( substr($date->format('Y'), 2) !== strval($year) ||
                      $date->format('m') !== strval($month) ||
                      $date->format('d') !== strval($day) );
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate Swedish personal identify numbers.
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

        $reg = '/^(\d{2}){0,1}(\d{2})(\d{2})(\d{2})([\-|\+]{0,1})?(\d{3})(\d{0,1})$/';
        preg_match($reg, $str, $match);

        if (!isset( $match ) || count($match) < 7) {
            return false;
        }

        $century = $match[1];
        $year    = $match[2];
        $month   = $match[3];
        $day     = $match[4];
        $sep     = $match[5];
        $num     = $match[6];
        $check   = $match[7];

        if (strlen($year) === 4) {
            $year = substr($year, 2);
        }

        $valid = self::luhn($year . $month . $day . $num) === intval($check);

        if ($valid && self::testDate($year, $month, $day)) {
            return $valid;
        }

        return $valid && self::testDate($year, $month, ( intval($day) - 60 ));
    }
}
