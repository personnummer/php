# personnummer

* Validate Swedish personnummer (civic numbers), samordningsnummer (coordination numbers), reservnummer (reserve numbers), Danish CPR numbers, and Norwegian birth numbers.
* It is important to note that this library provides only validation.
* Reserve numbers are arbitrarily constructed in different
ways, but may look alike. **This means that a given reserve number may also identify as another type of reserve number.**
The helper methods for each individual reserve number type will only indicate that the current reserve number has passed
validation for that type.

### Different types of reserve numbers
Different reserve number standards are used in specific Swedish regions and may share
similarity in their construction.

| Abbreviation | Description                                        |
| -------------|----------------------------------------------------|
| VGR          | Västra Götalandsregionen                           |
| SLL          | Region Stockholm (former Stockholm läns landsting) |
| RVB          | Region Västerbotten                                |

## Installation

```
composer require pinefox/personnummer
```

## Methods
#### Static
| Method | Arguments                                                              | Returns  |
| -------|:-----------------------------------------------------------------------|---------:|
| parse  | string identificationNumber, [ array options<sup>[*](#options)</sup> ] | Instance |
| valid  | string identificationNumber, [ array options<sup>[*](#options)</sup> ] | bool     |

#### Instance
| Method                    | Arguments       | Returns |
| --------------------------|:----------------|--------:|
| format                    | bool longFormat | string  |
| getAge                    | none            | int     |
| isMale                    | none            | bool    |
| isFemale                  | none            | bool    |
| isPersonalIdentityNumber  | none            | bool    |
| isCoordinationNumber      | none            | bool    |
| isReserveNumber           | none            | bool    |
| isTNumber                 | none            | bool    |
| isVgrReserveNumber        | none            | bool    |
| isSllReserveNumber        | none            | bool    |
| isRvbReserveNumber        | none            | bool    |
| isDanishCprNumber         | none            | bool    |
| isNorwegianBirthNumber    | none            | bool    |

| Property | Type   | Description                 |
| ---------|:-------|----------------------------:|
| century  | string | Century, two digits         |
| year     | string | Year, two digits            |
| fullYear | string | Year, four digits           |
| month    | string | Month, two digits           |
| day      | string | Day, two digits             |
| sep      | string | Separator (-/+)             |
| num      | string | Suffix number, three digits |
| check    | string | Luhn check digit, one digit |

## Errors
When an identification number is invalid a PersonnummerException is thrown.

## Options
| Option                         | Type | Default | Description                                                   |
| -------------------------------|:-----|:--------|:-------------------------------------------------------------:|
| allowPersonalIdentityNumber    | bool | true    | Accept Swedish personal identity numbers (YYMMDD-XXXX).       |
| allowCoordinationNumber        | bool | true    | Accept coordination numbers.                                  |
| allowTNumber                   | bool | true    | Accept reserve numbers with single character in 9th position. |
| allowVgrReserveNumber          | bool | true    | Accept VGR reserve numbers (see specification).               |
| allowSllReserveNumber          | bool | true    | Accept SLL reserve numbers (see specification).               |
| allowRvbReserveNumber          | bool | true    | Accept RVB reserve numbers (see specification).               |
| allowDanishCprNumber           | bool | true    | Accept Danish CPR numbers (DDMMYY-SSSS format).               |
| allowNorwegianBirthNumber      | bool | true    | Accept Norwegian birth numbers (11-digit format).             |

## Examples

### Validation

```php
use Personnummer\Personnummer;

Personnummer::valid(1212121212);
//=> true

Personnummer::valid('20121212-1212');
//=> true
```

### Format
```php
use Personnummer\Personnummer;

// Short format (YYMMDD-XXXX)
(new Personnummer(1212121212))->format();
//=> 121212-1212

// Short format for 100+ years old
(new Personnummer('191212121212'))->format();
//=> 121212+1212

// Long format (YYYYMMDDXXXX)
(new Personnummer('1212121212'))->format(true);
//=> 201212121212
```

### Get Age
```php
use Personnummer\Personnummer;

(new Personnummer('1212121212'))->age;
//=> 7
```

### Get Sex
```php
use Personnummer\Personnummer;

(new Personnummer('1212121212'))->isMale();
//=> true
(new Personnummer('1212121212'))->isFemale();
//=> false
```

### Danish CPR Numbers
```php
use Personnummer\Personnummer;

// Validate Danish CPR
Personnummer::valid('010499-9995', ['allowDanishCprNumber' => true]);
//=> true

// Format Danish CPR  
(new Personnummer('0104999995', ['allowDanishCprNumber' => true]))->format();
//=> 010499-9995

// Check if it's Danish CPR
(new Personnummer('010499-9995', ['allowDanishCprNumber' => true]))->isDanishCprNumber();
//=> true

// Century determination based on 7th digit and year
(new Personnummer('010499-9995', ['allowDanishCprNumber' => true]))->fullYear;
//=> 1999 (digit 9, year 99 = 1900s)
```

### Norwegian Birth Numbers
```php
use Personnummer\Personnummer;

// Validate Norwegian birth number
Personnummer::valid('03016213704');
//=> true

// Check if it's Norwegian birth number
(new Personnummer('03016213704'))->isNorwegianBirthNumber();
//=> true

// Format Norwegian birth number (no separator)
(new Personnummer('03016213704'))->format();
//=> 03016213704

// Gender determination
(new Personnummer('03016213704'))->isMale(); // Check digit 4 is even = male
//=> true
```

See [PersonnummerTest.php](tests/PersonnummerTest.php) for more examples.

## License

MIT
