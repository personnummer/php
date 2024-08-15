<?php

namespace Personnummer;

use DateTime;

/**
 * Class Personnummer
 *
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

    private $isNorwegianBirthNumber = false;

    /**
     * If VGR reserve number, replace 9th position character with mapped value and calculate check digit.
     *
     * @var int[]
     */
    private $vgrMapping = [
        'K' => 5,
        'M' => 7,
        'X' => 8,
    ];

    /**
     * Personnummer constructor.
     *
     *
     * @throws PersonnummerException When $ssn is unparsable or invalid
     */
    public function __construct(string $ssn, array $options = [])
    {
        $ssn = $this->checkIfReserveNumber($ssn);

        $this->options = $this->parseOptions($options);

        if ($this->isNorwegianBirthNumber()) {
            $this->parts = $this->getNorwegianBirthNumberParts($ssn);
        } elseif ($this->isSllReserveNumber()) {
            $this->parts = $this->getSllParts($ssn);
        } else {
            $this->parts = $this->getParts($ssn);
        }

        if (! $this->isValid()) {
            throw new PersonnummerException();
        }
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

    private function checkIfReserveNumber($ssn): string
    {
        $letterPosition = $this->containsLetter($ssn);

        if (is_numeric($letterPosition)) {
            // Store reserve letter:
            $this->reserveNumberCharacter = $ssn[$letterPosition];

            // Replace letter with integer value 1 and evaluate as usual:
            $ssn[$letterPosition] = 1;
        }
        // Starting with 99 indicates ssl reserve number
        elseif (substr($ssn, 0, 2) === '99' && strlen($ssn) === 12) {
            $this->isSllReserve = true;
        }
        // 11 digits is interpreted as norwegian birth number
        elseif (preg_match('/^\d{11}$/i', $ssn)) {
            $this->isNorwegianBirthNumber = true;
        }

        return $ssn;
    }

    private function parseOptions(array $options): array
    {
        $defaultOptions = [
            'allowCoordinationNumber' => true,
            'allowTNumber' => true,
            'allowVgrReserveNumber' => true,
            'allowSllReserveNumber' => true,
            'allowRvbReserveNumber' => true,
            'allowNorwegianBirthNumber' => true,
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

    public static function parse(string $ssn, array $options = []): PersonnummerInterface
    {
        return new self($ssn, $options);
    }

    public static function valid(string $ssn, array $options = []): bool
    {
        try {
            return self::parse($ssn, $options)->isValid();
        } catch (PersonnummerException $exception) {
            return false;
        }
    }

    public function format(bool $longFormat = false): string
    {
        $parts = $this->parts;

        if ($this->isSllReserve) {
            $format = '99%1$s%2$s%6$s%7$s';
        } elseif ($this->isNorwegianBirthNumber()) {
            $format = '%4$s%3$s%2$s%6$s%7$s';
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

    /**
     * Divide identification number into parts
     */
    private function getParts(string $ssn): array
    {
        // phpcs:ignore
        $reg = '/^(?\'century\'\d{2}){0,1}(?\'year\'\d{2})(?\'month\'\d{2})(?\'day\'\d{2})(?\'sep\'[\+\-\s]?)(?\'num\'(?!000)\d{3})(?\'check\'\d)$/';

        preg_match($reg, $ssn, $match);

        if (empty($match)) {
            throw new PersonnummerException();
        }

        // Remove numeric matches
        $parts = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);

        // Only allow these separators:
        if (! in_array($parts['sep'], ['', '-', '+'], true)) {
            throw new PersonnummerException();
        }

        if (! empty($parts['century'])) {
            if (date('Y') - intval(strval($parts['century']).strval($parts['year'])) < 100) {
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

        $parts['fullYear'] = $parts['century'].$parts['year'];

        return $parts;
    }

    private function getSllParts(string $ssn): array
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

        if (! empty($parts['century'])) {
            if (date('Y') - intval(strval($parts['century']).strval($parts['year'])) < 100) {
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

        $parts['fullYear'] = $parts['century'].$parts['year'];

        return $parts;
    }

    private function getNorwegianBirthNumberParts(string $ssn): array
    {
        // phpcs:ignore
        $reg = '/(?\'day\'\d{2})(?\'month\'\d{2})(?\'year\'\d{2})(?\'num\'\d{3})(?\'check\'\d{2})/';

        preg_match($reg, $ssn, $match);

        if (empty($match)) {
            throw new PersonnummerException();
        }

        $parts = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
        $parts['century'] = $this->getCenturyForNorwegianBirthNumber($parts['num'], $parts['year']);

        $parts['sep'] = '';
        $parts['fullYear'] = $parts['century'].$parts['year'];

        return $parts;
    }

    private function getCenturyForNorwegianBirthNumber(int $individualNumber, int $year): int
    {
        if ($individualNumber <= 499) {
            return 19;
        }

        if ($individualNumber <= 749 && $year >= 54) {
            return 18;
        }

        if ($individualNumber >= 900 && $year >= 40) {
            return 19;
        }

        if ($year <= 39) {
            return 20;
        }

        return -1;
    }

    /**
     * Parse numbers
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
                $this->parts['century'].$this->parts['year']
            );
        }

        $number = $this->parts['year'].$this->parts['month'].$this->parts['day'].$this->parts['num'];
        $validCheck = $this->getLuhnCheckDigit($number) === (int) $this->parts['check'];

        return $validDate && $validCheck;
    }

    private function parseTNumber(): bool
    {
        //Attempt to parse this as a "T-number", replacing T (or maybe R or any character) with int 1.
        //
        if ($this->isSllReserve) {
            return false;
        }

        // Without a reserve number character, this cannot be a "T-number":
        if (! $this->reserveNumberCharacter) {
            return false;
        }

        $validDate = checkdate(
            $this->parts['month'],
            $this->parts['day'],
            $this->parts['century'].$this->parts['year']
        );

        $number = $this->parts['year'].$this->parts['month'].$this->parts['day'].$this->parts['num'];
        $validCheck = $this->getLuhnCheckDigit($number) === (int) $this->parts['check'];

        return $validDate && $validCheck;
    }

    private function parseVgrNumber(): bool
    {
        if ($this->isSllReserve) {
            return false;
        }

        // VGR numbers have a limited number of characters, see map:
        $this->setReserveNumberCharForVgr();

        $number = $this->parts['year'].$this->parts['month'].$this->parts['day'].$this->parts['num'];
        $validCheck = $this->getLuhnCheckDigit($number) === (int) $this->parts['check'];

        $this->isVgrReserve = $validCheck && $this->validateNumPartsForVgr();

        return $this->isVgrReserve;
    }

    private function validateNumPartsForVgr(): bool
    {
        $number = $this->parts['num'][1].$this->parts['num'][2];

        if (in_array($this->reserveNumberCharacter, ['M', 'K'])) {
            $modulus = (int) $number % 2;

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
        if ($this->reserveNumberCharacter === 'X' && (int) $number > 79 && (int) $number < 90) {
            return true;
        }

        return false;
    }

    private function setReserveNumberCharForVgr(): void
    {
        if (array_key_exists($this->reserveNumberCharacter, $this->vgrMapping)) {
            $this->parts['num'][0] = $this->vgrMapping[$this->reserveNumberCharacter];
        }
    }

    private function parseSllNumber(): bool
    {
        if (! $this->isSllReserveNumber()) {
            return false;
        }

        $number = $this->parts['century'].$this->parts['year'].$this->parts['num'];
        $validCheck = $this->getLuhnCheckDigit($number) === (int) $this->parts['check'];

        $this->isSllReserve = $validCheck && $this->validateNumPartsForSll();

        return $this->isSllReserve;
    }

    private function validateNumPartsForSll(): bool
    {
        $parts = $this->parts;
        $now = new DateTime();

        return (int) ($parts['fullYear']) <= $now->format('Y') + 1
            && (int) ($parts['fullYear']) > 1870;
    }

    private function parseRvbNumber(): bool
    {
        if ($this->isSllReserve) {
            return false;
        }

        $validDate = checkdate(
            $this->parts['month'],
            $this->parts['day'],
            $this->parts['century'].$this->parts['year']
        );

        $this->isRvbReserve = $validDate && $this->reserveNumberCharacter && $this->validateNumPartsForRvb();

        return $this->isRvbReserve;
    }

    private function validateNumPartsForRvb(): bool
    {
        if ($this->parts['fullYear'] < 2000) {
            // If this individual is born in 20th century:
            if (! in_array($this->parts['num'][1], [6, 9], false)) {
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

    private function parseNorwegianBirthNumber(): bool
    {
        $validDate = checkdate(
            $this->parts['month'],
            $this->parts['day'],
            $this->parts['fullYear']
        );

        $validCheck = $this->getNorwegianCheckDigits() == $this->parts['check'];

        $this->isNorwegianBirthNumber = $validDate && $validCheck;

        return $this->isNorwegianBirthNumber;
    }

    private function getNorwegianCheckDigits(): int
    {
        $number = $this->parts['day'].$this->parts['month'].$this->parts['year'].$this->parts['num'];
        $digits = array_map('intval', str_split($number));

        $firstCheckDigitSequence = [3, 7, 6, 1, 8, 9, 4, 5, 2];
        $secondCheckDigitSequence = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

        $firstCheckDigit = $this->getWeightedMod11($digits, $firstCheckDigitSequence);
        $digits[] = $firstCheckDigit;
        $secondCheckDigit = $this->getWeightedMod11($digits, $secondCheckDigitSequence);

        return $firstCheckDigit.$secondCheckDigit;
    }

    private function getWeightedMod11(array $digits, array $sequence): int
    {
        $index = 0;

        $checksum = array_reduce(
            $sequence,
            function ($total, $number) use ($digits, &$index) {
                if (isset($digits[$index])) {
                    $total += $number * $digits[$index];
                }
                $index++;

                return $total;
            },
            0
        );

        $remainder = $checksum % 11;

        if ($remainder === 0) {
            return 0;
        }

        return 11 - $remainder;
    }

    /**
     * Boolean checks
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

        if ($this->options['allowNorwegianBirthNumber'] && $this->parseNorwegianBirthNumber()) {
            return true;
        }

        return false;
    }

    public function isMale(): bool
    {
        if ($this->reserveNumberCharacter === 'X' && $this->isVgrReserveNumber()) {
            return false;
        }

        $parts = $this->parts;
        $genderDigit = substr($parts['num'], -1);

        return (bool) ($genderDigit % 2);
    }

    public function isFemale(): bool
    {
        if ($this->reserveNumberCharacter === 'X' && $this->isVgrReserveNumber()) {
            return false;
        }

        return ! $this->isMale();
    }

    public function isCoordinationNumber(): bool
    {
        $parts = $this->parts;

        return checkdate((int) $parts['month'], $parts['day'] - 60, $parts['fullYear']);
    }

    public function isReserveNumber(): bool
    {
        return $this->isTNumber() ||
            $this->isSllReserveNumber() ||
            $this->isVgrReserveNumber() ||
            $this->isRvbReserveNumber();
    }

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

    public function isNorwegianBirthNumber(): bool
    {
        return $this->isNorwegianBirthNumber;
    }

    /**
     * Helpers
     */

    /**
     * Find integer position of letter, or return bool false.
     *
     * @return false|int
     */
    private function containsLetter(string $ssn)
    {
        $ssn = strtoupper($ssn);

        $letters = [
            'T', 'R', 'S', 'U', 'W', 'X', 'J', 'K', 'L', 'M', 'N', 'D',
        ];

        foreach ($letters as $letter) {
            $positionFound = strpos($ssn, $letter);

            // If this is 10 or 11 digits long, the reserve number character should be in 7th position:
            if (is_numeric($positionFound) && strlen($ssn) < 12 && $positionFound !== 7) {
                throw new PersonnummerException();
            }

            // If this is more than 12 digits long, the reserve number character should be in 9th position:
            if (is_numeric($positionFound) && strlen($ssn) > 12 && $positionFound !== 9) {
                throw new PersonnummerException();
            }

            if ($positionFound !== false) {
                return $positionFound;
            }
        }

        return false;
    }

    /**
     * The Luhn algorithm.
     *
     * @param  string  $str  String to run the Luhn algorithm on.
     */
    private static function getLuhnCheckDigit(string $str): int
    {
        $sum = 0;

        for ($i = 0; $i < strlen($str); $i++) {
            $v = (int) $str[$i];
            $v *= 2 - ($i % 2);

            if ($v > 9) {
                $v -= 9;
            }

            $sum += $v;
        }

        return (int) ceil($sum / 10) * 10 - $sum;
    }
}
