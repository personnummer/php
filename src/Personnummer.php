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

    private $isVgrReserve = false;

    private $isSllReserve = false;

    private $isRvbReserve = false;

    /**
     * If VGR reserve number, replace 9th position character with mapped value and calculate check digit.
     * @var int[]
     */
    private $vgrMapping = [
        'K' => 5,
        'M' => 7,
        'X' => 8,
    ];

    /**
     *
     * @param string $ssn
     * @param array $options
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
        if ($this->reserveNumberCharacter === 'X' && $this->isVgrReserveNumber()) {
            return false;
        }

        $parts = $this->parts;
        $genderDigit = substr($parts['num'], -1);

        return (bool)($genderDigit % 2);
    }

    /**
     * Check if a Swedish social security number is for a female.
     *
     * @return bool
     */
    public function isFemale(): bool
    {
        if ($this->reserveNumberCharacter === 'X' && $this->isVgrReserveNumber()) {
            return false;
        }

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

        if ($this->isSllReserve) {
            $format = '99%1$s%2$s%6$s%7$s';
        } elseif ($longFormat) {
            $format = '%1$s%2$s%3$s%4$s%6$s%7$s';
        } else {
            $format = '%2$s%3$s%4$s%5$s%6$s%7$s';
        }

        if ($this->reserveNumberCharacter) {
            $parts['num'][0] = $this->reserveNumberCharacter;
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

        return checkdate((int)$parts['month'], $parts['day'] - 60, $parts['fullYear']);
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
                $baseYear = date('Y');
            }
            $parts['century'] = substr(($baseYear - (($baseYear - $parts['year']) % 100)), 0, 2);
        }

        $parts['fullYear'] = $parts['century'] . $parts['year'];

        return $parts;
    }

    private static function getSllParts(string $ssn): array
    {
        // phpcs:ignore
        $reg = '/^(?\'sll\'\d{2}){0,}(?\'century\'\d{2})(?\'year\'\d{2})(?\'num\'(?!000000)\d{5})(?\'check\'\d)$/';

        preg_match($reg, $ssn, $match);

        if (empty($match)) {
            throw new PersonnummerException();
        }

        $parts = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
        $parts['month'] = '01';
        $parts['day'] = '01';

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
                $baseYear = date('Y');
            }
            $parts['century'] = substr(($baseYear - (($baseYear - $parts['year']) % 100)), 0, 2);
        }

        $parts['fullYear'] = $parts['century'] . $parts['year'];

        return $parts;
    }

    /**
     * Find integer position of letter, or return bool false.
     * @param string $ssn
     * @return false|int
     */
    private static function containsLetter(string $ssn)
    {
        $ssn = strtoupper($ssn);

        $letters = [
            'T', 'R', 'S', 'U', 'W', 'X', 'J', 'K', 'L', 'M', 'N', 'D'
        ];

        foreach ($letters as $letter) {
            $positionFound = strpos($ssn, $letter);

            if ($positionFound !== false) {
                return $positionFound;
            }
        }

        return false;
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
            $v = (int)$str[$i];
            $v *= 2 - ($i % 2);

            if ($v > 9) {
                $v -= 9;
            }

            $sum += $v;
        }

        return (int)ceil($sum / 10) * 10 - $sum;
    }

    /**
     * Check if SSN is a "reserve number" (reservnummer) and set properties accordingly.
     * @param $ssn
     * @return string
     */
    private function checkIfReserveNumber($ssn): string
    {
        $letterPosition = self::containsLetter($ssn);

        if (is_numeric($letterPosition)) {
            // Store reserve letter:
            $this->reserveNumberCharacter = $ssn[$letterPosition];

            // Replace letter with integer value 1 and evaluate as usual:
            $ssn[$letterPosition] = 1;
        }

        if (substr($ssn, 0, 2) === '99' && strlen($ssn) === 12) {
            $this->isSllReserve = true;
        }

        return $ssn;
    }

    /**
     * Personnummer constructor.
     *
     * @param string $ssn
     * @param array $options
     *
     * @throws PersonnummerException When $ssn is unparsable or invalid
     */
    public function __construct(string $ssn, array $options = [])
    {
        $ssn = $this->checkIfReserveNumber($ssn);

        $this->options = $this->parseOptions($options);

        if ($this->isSllReserveNumber()) {
            $this->parts = self::getSllParts($ssn);
        } else {
            $this->parts = self::getParts($ssn);
        }

        if (!$this->isValid()) {
            throw new PersonnummerException();
        }
    }

    /**
     * Superset of any other type of reserve number.
     * @return bool
     */
    public function isReserveNumber(): bool
    {
        return $this->isTNumber() ||
            $this->isSllReserveNumber() ||
            $this->isVgrReserveNumber() ||
            $this->isRvbReserveNumber();
    }

    /**
     * Generic reserve number, T/R replaced by value 1.
     * @return bool
     */
    public function isTNumber(): bool
    {
        return $this->reserveNumberCharacter !== null;
    }

    public function isVgrReserveNumber(): bool
    {
        return $this->isVgrReserve;
    }

    public function isSllReserveNumber(): bool
    {
        return $this->isSllReserve;
    }

    public function isRvbReserveNumber(): bool
    {
        return $this->isRvbReserve;
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

        if ($this->isSllReserveNumber()) {
            return -1;
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
        // If this validates as ssn, return true:
        if ($this->parseSsn()) {
            return true;
        }

        if ($this->options['allowTNumber'] && $this->parseTNumber()) {
            return true;
        }

        if ($this->options['allowVgrReserveNumber'] && $this->parseVgrNumber()) {
            return true;
        }

        if ($this->options['allowSllReserveNumber'] && $this->parseSllNumber()) {
            return true;
        }

        if ($this->options['allowRvbReserveNumber'] && $this->parseRvbNumber()) {
            return true;
        }

        return false;
    }

    /**
     * Attempt to parse this as a regular personnummer (or coordination number).
     * @return bool
     */
    private function parseSsn(): bool
    {
        // This cannot pass as ssn if there is a reserve number character:
        if ($this->reserveNumberCharacter || $this->isSllReserve) {
            return false;
        }

        if ($this->options['allowCoordinationNumber'] && $this->isCoordinationNumber()) {
            $validDate = true;
        } else {
            $validDate = checkdate(
                $this->parts['month'],
                $this->parts['day'],
                $this->parts['century'] . $this->parts['year']
            );
        }

        $check = $this->parts['year'] . $this->parts['month'] . $this->parts['day'] . $this->parts['num'];
        $validCheck = self::luhn($check) === (int)$this->parts['check'];

        return $validDate && $validCheck;
    }

    /**
     * Attempt to parse this as a "T-number", replacing T (or maybe R or any character) with int 1.
     * @return bool
     */
    private function parseTNumber(): bool
    {
        if ($this->isSllReserve) {
            return false;
        }

        // Without a reserve number character, this cannot be a "T-number":
        if (!$this->reserveNumberCharacter) {
            return false;
        }

        $validDate = checkdate(
            $this->parts['month'],
            $this->parts['day'],
            $this->parts['century'] . $this->parts['year']
        );

        $check = $this->parts['year'] . $this->parts['month'] . $this->parts['day'] . $this->parts['num'];
        $validCheck = self::luhn($check) === (int)$this->parts['check'];

        return $validDate && $validCheck;
    }

    /**
     * Attempt to parse this as a VGR number.
     * @return bool
     */
    private function parseVgrNumber(): bool
    {
        if ($this->isSllReserve) {
            return false;
        }

        // VGR numbers have a limited number of characters, see map:
        $this->setReserveNumberCharForVgr();
        $check = $this->parts['year'] . $this->parts['month'] . $this->parts['day'] . $this->parts['num'];
        $validCheck = self::luhn($check) === (int)$this->parts['check'];
        $this->isVgrReserve = $validCheck && $this->validateNumPartsForVgr();

        return $this->isVgrReserve;
    }

    /**
     * Attempt to parse as SLL number.
     * @return bool
     */
    private function parseSllNumber(): bool
    {
        if (!$this->isSllReserveNumber()) {
            return false;
        }

        $check = $this->parts['century'] . $this->parts['year'] . $this->parts['num'];
        $validCheck = self::luhn($check) === (int)$this->parts['check'];
        $this->isSllReserve = $validCheck && $this->validateNumPartsForSll();

        return $this->isSllReserve;
    }

    /**
     * Attempt to parse as RVB number.
     * @return bool
     */
    private function parseRvbNumber(): bool
    {
        if ($this->isSllReserve) {
            return false;
        }

        $validDate = checkdate(
            $this->parts['month'],
            $this->parts['day'],
            $this->parts['century'] . $this->parts['year']
        );

        $this->isRvbReserve = $validDate && $this->reserveNumberCharacter && $this->validateNumPartsForRvb();

        return $this->isRvbReserve;
    }

    private function validateNumPartsForVgr(): bool
    {
        $number = $this->parts['num'][1] . $this->parts['num'][2];

        if (in_array($this->reserveNumberCharacter, ['M', 'K'])) {
            $modulus = (int)$number % 2;

            // For female gender (K), the number should be even:
            if ($this->reserveNumberCharacter === 'K' && $modulus === 0) {
                return true;
            }

            // For male gender (M), the number should be odd:
            if ($this->reserveNumberCharacter === 'M' && $modulus === 1) {
                return true;
            }
        }

        // For unknown gender (X),the number should be between 80-89:
        if ($this->reserveNumberCharacter === 'X' && (int)$number > 79 && (int)$number < 90) {
            return true;
        }

        return false;
    }

    private function validateNumPartsForRvb(): bool
    {
        if ($this->parts['fullYear'] < 2000) {
            // If this individual is born in 20th century:
            if (!in_array($this->parts['num'][1], [6, 9], false)) {
                // Number X in YYMMDD-RXNN should be 6 or 9:
                return false;
            }
        } else {
            // If this individual is born in 21th century:
            if ($this->parts['num'][1] !== '2') {
                // Number X in YYMMDD-RXNN should be 2:
                return false;
            }
        }

        return true;
    }

    /**
     * Make sure the the issuance date of the sll-reserve number is not greater than the current year + 1.
     *
     * @return bool
     */
    private function validateNumPartsForSll(): bool
    {
        $parts = $this->parts;
        $now = new DateTime();

        return (int)($parts['fullYear']) <= $now->format('Y') + 1
            && (int)($parts['fullYear']) > 1870;
    }

    private function setReserveNumberCharForVgr(): void
    {
        if (array_key_exists($this->reserveNumberCharacter, $this->vgrMapping)) {
            $this->parts['num'][0] = $this->vgrMapping[$this->reserveNumberCharacter];
        }
    }

    private function parseOptions(array $options): array
    {
        $defaultOptions = [
            'allowCoordinationNumber' => true,
            'allowTNumber' => true,
            'allowVgrReserveNumber' => true,
            'allowSllReserveNumber' => true,
            'allowRvbReserveNumber' => true,
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
