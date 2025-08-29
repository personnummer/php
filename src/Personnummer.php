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

    private $isDanishCprNumber = false;

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
     * @throws PersonnummerException When $identificationNumber is unparsable or invalid
     */
    public function __construct(string $identificationNumber, array $options = [])
    {
        $this->options = $this->parseOptions($options);

        // Try different formats based on options and format detection

        // Check for Danish CPR if allowed (must have explicit dash to avoid conflicts)
        if ($this->options['allowDanishCprNumber'] && $this->looksLikeDanishCpr($identificationNumber)) {
            $this->isDanishCprNumber = true;
            $this->parts = $this->getDanishCprParts($identificationNumber);
        } elseif ($this->options['allowNorwegianBirthNumber'] && preg_match('/^\d{11}$/i', $identificationNumber)) {
            $this->isNorwegianBirthNumber = true;
            $this->parts = $this->getNorwegianBirthNumberParts($identificationNumber);
        } elseif (
            $this->options['allowSllReserveNumber'] &&
            substr($identificationNumber, 0, 2) === '99' &&
            strlen($identificationNumber) === 12
        ) {
            $this->isSllReserve = true;
            $this->parts = $this->getSllParts($identificationNumber);
        } else {
            if ($this->options['allowPersonalIdentityNumber']) {
                $identificationNumber = $this->checkIfReserveNumber($identificationNumber);
                $this->parts = $this->getParts($identificationNumber);
            } else {
                // If Personal Identity Number format is disabled, we might still have T-numbers or reserve numbers
                // These will be handled in isValid() method
                $this->parts = $this->getParts($identificationNumber);
            }
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

    private function checkIfReserveNumber($identificationNumber): string
    {
        $letterPosition = $this->containsLetter($identificationNumber);

        if (is_numeric($letterPosition)) {
            // Store reserve letter:
            $this->reserveNumberCharacter = $identificationNumber[$letterPosition];

            // Replace letter with integer value 1 and evaluate as usual:
            $identificationNumber[$letterPosition] = 1;
        }

        return $identificationNumber;
    }

    private function parseOptions(array $options): array
    {
        $defaultOptions = [
            'allowPersonalIdentityNumber' => true,
            'allowCoordinationNumber' => true,
            'allowTNumber' => true,
            'allowVgrReserveNumber' => true,
            'allowSllReserveNumber' => true,
            'allowRvbReserveNumber' => true,
            'allowNorwegianBirthNumber' => true,
            'allowDanishCprNumber' => true,
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

    public static function parse(string $identificationNumber, array $options = []): PersonnummerInterface
    {
        return new self($identificationNumber, $options);
    }

    public static function valid(string $identificationNumber, array $options = []): bool
    {
        try {
            return self::parse($identificationNumber, $options)->isValid();
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
        } elseif ($this->isDanishCprNumber()) {
            if ($longFormat) {
                $format = '%4$s%3$s%2$s%6$s%7$s';
            } else {
                $format = '%4$s%3$s%2$s-%6$s%7$s';
            }
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
    private function getParts(string $identificationNumber): array
    {
        // phpcs:ignore
        $reg = '/^(?\'century\'\d{2}){0,1}(?\'year\'\d{2})(?\'month\'\d{2})(?\'day\'\d{2})(?\'sep\'[\+\-\s]?)(?\'num\'(?!000)\d{3})(?\'check\'\d)$/';

        preg_match($reg, $identificationNumber, $match);

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

    private function getSllParts(string $sllNumber): array
    {
        // phpcs:ignore
        $reg = '/^(?\'sll\'\d{2}){0,}(?\'century\'\d{2})(?\'year\'\d{2})(?\'num\'(?!000000)\d{5})(?\'check\'\d)$/';

        preg_match($reg, $sllNumber, $match);

        if (empty($match)) {
            throw new PersonnummerException();
        }

        $parts = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
        $parts['month'] = '01';
        $parts['day'] = '01';

        if (! empty($parts['century'])) {
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

    private function getNorwegianBirthNumberParts(string $norwegianNumber): array
    {
        // phpcs:ignore
        $reg = '/(?\'day\'\d{2})(?\'month\'\d{2})(?\'year\'\d{2})(?\'num\'\d{3})(?\'check\'\d{2})/';

        preg_match($reg, $norwegianNumber, $match);

        if (empty($match)) {
            throw new PersonnummerException();
        }

        $parts = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
        $parts['century'] = $this->getCenturyForNorwegianBirthNumber($parts['num'], $parts['year']);

        $parts['sep'] = '';
        $parts['fullYear'] = $parts['century'] . $parts['year'];

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
     * Parse Swedish personal identity numbers
     */
    private function parsePersonalIdentityNumber(): bool
    {
        // This cannot pass as personal identity number if there is a reserve number character:
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

        $number = $this->parts['year'] . $this->parts['month'] . $this->parts['day'] . $this->parts['num'];
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
            $this->parts['century'] . $this->parts['year']
        );

        $number = $this->parts['year'] . $this->parts['month'] . $this->parts['day'] . $this->parts['num'];
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

        $number = $this->parts['year'] . $this->parts['month'] . $this->parts['day'] . $this->parts['num'];
        $validCheck = $this->getLuhnCheckDigit($number) === (int) $this->parts['check'];

        $this->isVgrReserve = $validCheck && $this->validateNumPartsForVgr();

        return $this->isVgrReserve;
    }

    private function validateNumPartsForVgr(): bool
    {
        $number = $this->parts['num'][1] . $this->parts['num'][2];

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

        $number = $this->parts['century'] . $this->parts['year'] . $this->parts['num'];
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
            $this->parts['century'] . $this->parts['year']
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
        $number = $this->parts['day'] . $this->parts['month'] . $this->parts['year'] . $this->parts['num'];
        $digits = array_map('intval', str_split($number));

        $firstCheckDigitSequence = [3, 7, 6, 1, 8, 9, 4, 5, 2];
        $secondCheckDigitSequence = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

        $firstCheckDigit = $this->getWeightedMod11($digits, $firstCheckDigitSequence);
        $digits[] = $firstCheckDigit;
        $secondCheckDigit = $this->getWeightedMod11($digits, $secondCheckDigitSequence);

        return $firstCheckDigit . $secondCheckDigit;
    }

    private function getDanishCprParts(string $danishCprNumber): array
    {
        // Remove any dash separator
        $danishCprNumber = str_replace('-', '', $danishCprNumber);

        // phpcs:ignore
        $reg = '/^(?\'day\'\d{2})(?\'month\'\d{2})(?\'year\'\d{2})(?\'num\'\d{3})(?\'check\'\d)$/';

        preg_match($reg, $danishCprNumber, $match);

        if (empty($match)) {
            throw new PersonnummerException();
        }

        $parts = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);

        // Determine century based on year and 7th digit (first digit of num)
        $parts['century'] = $this->getDanishCprCentury($parts['year'], $parts['num'][0]);

        $parts['sep'] = '';
        $parts['fullYear'] = $parts['century'] . $parts['year'];

        return $parts;
    }

    private function getDanishCprCentury(string $year, string $seventhDigit): string
    {
        $yearInt = intval($year);
        $digitInt = intval($seventhDigit);

        // Rules for century determination
        if ($digitInt >= 0 && $digitInt <= 3) {
            // Born 1900-1999
            return '19';
        } elseif ($digitInt == 4 || $digitInt == 9) {
            // Born 2000-2099 if year is 00-36, else 1900-1999
            if ($yearInt >= 0 && $yearInt <= 36) {
                return '20';
            } else {
                return '19';
            }
        } elseif ($digitInt >= 5 && $digitInt <= 8) {
            // Born 1800-1899 if year is 00-57, else 2000-2099
            if ($yearInt >= 0 && $yearInt <= 57) {
                return '18';
            } else {
                return '20';
            }
        }

        return '19'; // Default fallback
    }

    private function parseDanishCpr(): bool
    {
        if (!$this->isDanishCprNumber()) {
            return false;
        }

        // Validate date - Danish CPR uses DDMMYY format
        $validDate = checkdate(
            intval($this->parts['month']),
            intval($this->parts['day']),
            intval($this->parts['fullYear'])
        );

        // Additional validation: year should be reasonable
        $currentYear = date('Y');
        $birthYear = intval($this->parts['fullYear']);

        // Reject if birth year is more than 1 year in the future
        if ($birthYear > $currentYear + 1) {
            $validDate = false;
        }

        // Reject if birth year would make person older than 120 years (unrealistic)
        if ($currentYear - $birthYear > 120) {
            $validDate = false;
        }
        // Since 2007, Denmark doesn't validate check digits due to running out of valid numbers
        // We only validate the format and date
        return $validDate;
    }

    private function looksLikePersonalIdentityNumber(string $identificationNumber): bool
    {
        // Personal Identity Number format has 12 digits or uses + separator for 100+ years old
        // This helps distinguish from Danish CPR 10-digit format
        $cleaned = str_replace(['-', '+'], '', $identificationNumber);

        // Check if it could be a valid Personal Identity Number format
        if (strlen($cleaned) === 12) {
            return true;
        }

        // Check for Personal Identity Number separator patterns
        if (strpos($identificationNumber, '+') !== false) {
            return true;
        }

        // Check if the pattern matches Personal Identity Number coordination number (day + 60)
        if (strlen($cleaned) === 10) {
            $day = intval(substr($cleaned, 4, 2));
            if ($day > 31 && $day <= 91) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeDanishCpr(string $identificationNumber): bool
    {
        // Accept both dash format (DDMMYY-XXXX) and 10-digit format (DDMMYYXXXX)
        if (preg_match('/^\d{6}-\d{4}$/', $identificationNumber)) {
            // Dash format - definitely Danish CPR
            $cleaned = str_replace('-', '', $identificationNumber);
        } elseif (preg_match('/^\d{10}$/', $identificationNumber)) {
            // 10-digit format - could be Danish CPR, but need to distinguish from Personal Identity Number
            $cleaned = $identificationNumber;
        } else {
            return false;
        }

        // Parse as Danish CPR format: DDMMYY
        $day = intval(substr($cleaned, 0, 2));
        $month = intval(substr($cleaned, 2, 2));
        $year = intval(substr($cleaned, 4, 2));

        // Basic date validation (Danish CPR uses DDMMYY format)
        if ($day < 1 || $day > 31 || $month < 1 || $month > 12) {
            return false;
        }

        // For 10-digit format without dash, apply heuristics to avoid Personal Identity Number conflicts
        if (!strpos($identificationNumber, '-')) {
            // If parsed as Personal Identity Number format (YYMMDD), check if it would make more sense
            $swedishYear = $day;    // YY in Personal Identity Number format
            $swedishMonth = $month; // MM in Personal Identity Number format
            $swedishDay = $year;    // DD in Personal Identity Number format

            // Only reject if there's clear evidence this should be Personal Identity Number format:

            // 1. If Danish CPR date is clearly invalid but Personal Identity Number would be valid
            if ($day > 31 && $swedishDay >= 1 && $swedishDay <= 31 && $swedishMonth >= 1 && $swedishMonth <= 12) {
                return false;
            }

            // 2. If Danish CPR month is invalid but Personal Identity Number is valid
            if ($month > 12 && $swedishMonth >= 1 && $swedishMonth <= 12) {
                return false;
            }

            // 3. Special case: if Personal Identity Number interpretation suggests coordination number (day > 60)
            //    AND Danish CPR interpretation has suspicious patterns, then reject
            if ($swedishDay > 60 && $swedishMonth >= 1 && $swedishMonth <= 12) {
                // Only reject if Danish day/month combination is uncommon
                // Allow common Danish CPR dates even if they could be coordination numbers
                if ($day > 20 || $month > 8) { // Arbitrary thresholds for "uncommon" dates
                    return false;
                }
            }

            // 4. Be conservative: if both interpretations are equally valid,
            //    prefer Personal Identity Number format unless there are strong Danish CPR indicators
            if ($swedishDay >= 1 && $swedishDay <= 31 && $swedishMonth >= 1 && $swedishMonth <= 12) {
                // Both formats seem valid - add bias toward Personal Identity Number format
                // Only accept as Danish CPR if there are specific Danish CPR patterns

                // Strong Danish CPR indicators: years that would be unrealistic
                // for Personal Identity Number birth years
                $currentYear = date('Y');
                $danishBirthYear1900s = 1900 + $year;
                $danishBirthYear2000s = 2000 + $year;

                // If Danish year interpretation gives a very reasonable current age (0-80),
                // while Personal Identity Number interpretation gives unlikely ages, prefer Danish CPR
                $danishAge1900s = $currentYear - $danishBirthYear1900s;
                $danishAge2000s = $currentYear - $danishBirthYear2000s;

                $swedishBirthYear1900s = 1900 + $swedishYear;
                $swedishBirthYear2000s = 2000 + $swedishYear;
                $swedishAge1900s = $currentYear - $swedishBirthYear1900s;
                $swedishAge2000s = $currentYear - $swedishBirthYear2000s;

                // If Danish CPR interpretation gives reasonable age (0-100)
                // but Personal Identity Number doesn't, prefer Danish CPR
                $danishReasonable = (($danishAge1900s >= 0 && $danishAge1900s <= 100) ||
                                   ($danishAge2000s >= 0 && $danishAge2000s <= 100));
                $swedishReasonable = (($swedishAge1900s >= 0 && $swedishAge1900s <= 100) ||
                                    ($swedishAge2000s >= 0 && $swedishAge2000s <= 100));

                if (!$danishReasonable || $swedishReasonable) {
                    // If Danish CPR doesn't give reasonable age, or Personal Identity Number also gives reasonable age,
                    // default to Personal Identity Number format
                    return false;
                }
            }
        }

        return true;
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
        // Check specific format types first if they were already identified
        if ($this->isDanishCprNumber()) {
            return $this->options['allowDanishCprNumber'] && $this->parseDanishCpr();
        }

        if ($this->isNorwegianBirthNumber()) {
            return $this->options['allowNorwegianBirthNumber'] && $this->parseNorwegianBirthNumber();
        }

        if ($this->isSllReserveNumber()) {
            return $this->options['allowSllReserveNumber'] && $this->parseSllNumber();
        }

        // For Personal Identity Number formats, try different validation methods
        if ($this->options['allowPersonalIdentityNumber'] && $this->parsePersonalIdentityNumber()) {
            return true;
        }

        if ($this->options['allowTNumber'] && $this->parseTNumber()) {
            return true;
        }

        if ($this->options['allowVgrReserveNumber'] && $this->parseVgrNumber()) {
            return true;
        }

        if ($this->options['allowRvbReserveNumber'] && $this->parseRvbNumber()) {
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

        // For Danish CPR, use the check digit (last digit of entire sequence)
        if ($this->isDanishCprNumber()) {
            $genderDigit = $parts['check'];
        } else {
            $genderDigit = substr($parts['num'], -1);
        }

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

    public function isDanishCprNumber(): bool
    {
        return $this->isDanishCprNumber;
    }

    public function isPersonalIdentityNumber(): bool
    {
        // A Personal Identity Number is one that:
        // 1. Is not identified as another specific format
        // 2. Passes Personal Identity Number format validation (parsePersonalIdentityNumber)
        // 3. Is allowed by the allowPersonalIdentityNumber option

        if ($this->isDanishCprNumber() || $this->isNorwegianBirthNumber() || $this->isSllReserveNumber()) {
            return false;
        }

        // Check if it's a reserve number type
        if (
            $this->reserveNumberCharacter || $this->isTNumber() ||
            $this->isVgrReserveNumber() || $this->isRvbReserveNumber()
        ) {
            return false;
        }

        // If we reach here and the number is valid, it should be a Personal Identity Number
        // This includes both regular Personal Identity Numbers and coordination numbers
        return $this->options['allowPersonalIdentityNumber'] && $this->parsePersonalIdentityNumber();
    }

    /**
     * Helpers
     */

    /**
     * Find integer position of letter, or return bool false.
     *
     * @return false|int
     */
    private function containsLetter(string $identificationNumber)
    {
        $identificationNumber = strtoupper($identificationNumber);

        $letters = [
            'T', 'R', 'S', 'U', 'W', 'X', 'J', 'K', 'L', 'M', 'N', 'D',
        ];

        foreach ($letters as $letter) {
            $positionFound = strpos($identificationNumber, $letter);

            // If this is 10 or 11 digits long, the reserve number character should be in 7th position:
            if (is_numeric($positionFound) && strlen($identificationNumber) < 12 && $positionFound !== 7) {
                throw new PersonnummerException();
            }

            // If this is more than 12 digits long, the reserve number character should be in 9th position:
            if (is_numeric($positionFound) && strlen($identificationNumber) > 12 && $positionFound !== 9) {
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
