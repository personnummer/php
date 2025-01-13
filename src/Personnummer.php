<?php

namespace Personnummer;

use DateTime;
use Exception;
use Psr\Clock\ClockInterface;

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
    private array $parts;

    private array $options;

    private ClockInterface $clock;

    private bool $isInterim;

    /**
     * @inheritDoc
     */
    public static function parse(string $ssn, array $options = []): PersonnummerInterface
    {
        return new self($ssn, $options);
    }

    /**
     * @inheritDoc
     */
    public function isMale(): bool
    {
        $parts       = $this->parts;
        $genderDigit = substr($parts['num'], -1);

        return (bool)($genderDigit % 2);
    }


    /**
     * @inheritDoc
     */
    public function isFemale(): bool
    {
        return !$this->isMale();
    }

    /**
     * @inheritDoc
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

    /**
     * @inheritDoc
     */
    public function isCoordinationNumber(): bool
    {
        $parts = $this->parts;

        return checkdate((int)$parts['month'], $parts['day'] - 60, $parts['fullYear']);
    }

    /**
     * @inheritDoc
     */
    public function isInterimNumber(): bool
    {
        return $this->isInterim;
    }

    /**
     * @inheritDoc
     */
    public static function valid(string $ssn, array $options = []): bool
    {
        try {
            return self::parse($ssn, $options)->isValid();
        } catch (PersonnummerException) {
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
        $reg = '/^(?\'century\'\d{2}){0,1}(?\'year\'\d{2})(?\'month\'\d{2})(?\'day\'\d{2})(?\'sep\'[\+\-]?)(?\'num\'(?!000)\d{3}|[TRSUWXJKLMN]\d{2})(?\'check\'\d)$/';
        preg_match($reg, $ssn, $match);

        if (empty($match)) {
            throw new PersonnummerException();
        }

        // Remove numeric matches
        $parts = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);

        if (!empty($parts['century'])) {
            if (date('Y') - (int)((string)$parts['century'] . (string)$parts['year']) < 100) {
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
        $parts['realDay']  = $parts['day'] > 60 ? $parts['day'] - 60 : $parts['day'];
        $parts['original'] = $ssn;
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

        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $v = (int)$str[$i];
            $v *= 2 - ($i % 2);

            if ($v > 9) {
                $v -= 9;
            }

            $sum += $v;
        }

        return (int)(ceil($sum / 10) * 10 - $sum);
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
        $this->clock = $this->options['clock'];
        $this->parts   = self::getParts($ssn);

        // Sanity checks.
        $ssn = trim($ssn);
        $len = strlen($ssn);
        if ($len > 13 || $len < 10) {
            throw new PersonnummerException(
                sprintf(
                    'Input string too %s',
                    $len < 10 ? 'short' : 'long'
                )
            );
        }

        if (!$this->isValid()) {
            throw new PersonnummerException();
        }
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

        $day = (int)$parts['day'];
        if ($this->isCoordinationNumber()) {
            $day -= 60;
        }

        $birthday = new DateTime(sprintf('%s%s-%s-%d', $parts['century'], $parts['year'], $parts['month'], $day));
        $diff = $birthday->diff($this->clock->now());
        return $diff->invert === 0 ? $diff->y : -$diff->y;
    }

    public function getDate(): DateTime
    {
        return DateTime::createFromFormat(
            'Ymd',
            $this->parts['fullYear'] . $this->parts['month'] . $this->parts['realDay']
        );
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

        // Correct interim if allowed.
        $interimTest = '/(?![-+])\D/';
        $this->isInterim = preg_match($interimTest, $parts['original']) !== 0;

        if ($this->options['allowInterimNumber'] === false && $this->isInterim) {
            throw new PersonnummerException(sprintf(
                '%s contains non-integer characters and options are set to not allow interim numbers',
                $parts['original']
            ));
        }

        $num = $parts['num'];
        if ($this->options['allowInterimNumber'] && $this->isInterim) {
            $num = preg_replace($interimTest, '1', $num);
        }

        if ($this->options['allowCoordinationNumber'] && $this->isCoordinationNumber()) {
            $validDate = true;
        } else {
            $validDate = checkdate($parts['month'], $parts['day'], $parts['century'] . $parts['year']);
        }

        $checkStr   = $parts['year'] . $parts['month'] . $parts['day'] . $num;
        $validCheck = self::luhn($checkStr) === (int)$parts['check'];

        return $validDate && $validCheck;
    }

    private function parseOptions(array $options): array
    {
        $defaultOptions = [
            'allowCoordinationNumber' => true,
            'allowInterimNumber' => false,
            'clock' => new SystemClock(),
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
