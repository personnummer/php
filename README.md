# personnummer

* Validate Swedish personnummer (civic numbers), samordningsnummer (coordination numbers) and reservnummer (reserve numbers).
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
| Method | Arguments                                                      | Returns  |
| -------|:---------------------------------------------------------------|---------:|
| parse  | string personnummer, [ array options<sup>[*](#options)</sup> ] | Instance |
| valid  | string personnummer, [ array options<sup>[*](#options)</sup> ] | bool     |

#### Instance
| Method               | Arguments       | Returns |
| ---------------------|:----------------|--------:|
| format               | bool longFormat | string  |
| getAge               | none            | int     |
| isMale               | none            | bool    |
| isFemale             | none            | bool    |
| isCoordinationNumber | none            | bool    |
| isReserveNumber      | none            | bool    |
| isTNumber            | none            | bool    |
| isVgrReserveNumber   | none            | bool    |
| isSllReserveNumber   | none            | bool    |
| isRvbReserveNumber   | none            | bool    |

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
When a personnummer is invalid a PersonnummerException is thrown.

## Options
| Option                  | Type | Default | Description                                                   |
| ------------------------|:-----|:--------|:-------------------------------------------------------------:|
| allowCoordinationNumber | bool | true    | Accept coordination numbers.                                  |
| allowTNumber            | bool | true    | Accept reserve numbers with single character in 9th position. |
| allowVgrReserveNumber   | bool | true    | Accept VGR reserve numbers (see specification).               |
| allowSllReserveNumber   | bool | true    | Accept SLL reserve numbers (see specification).               |
| allowRvbReserveNumber   | bool | true    | Accept RVB reserve numbers (see specification).               |

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

See [PersonnummerTest.php](tests/PersonnummerTest.php) for more examples.

## License

MIT
