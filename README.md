# personnummer [![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/personnummer/php/php.yml)](https://github.com/personnummer/php/actions) [![Packagist Version](https://img.shields.io/packagist/v/personnummer/personnummer)](https://packagist.org/packages/personnummer/personnummer) [![PHP from Packagist](https://img.shields.io/packagist/php-v/personnummer/personnummer)](https://packagist.org/packages/personnummer/personnummer)

Validate Swedish social security numbers.

## Installation

```
composer require personnummer/personnummer
```

## Methods
#### Static
| Method | Arguments                                                      | Returns  |
| -------|:---------------------------------------------------------------|---------:|
| parse  | string personnummer, [ array options<sup>[*](#options)</sup> ] | Instance |
| valid  | string personnummer, [ array options<sup>[*](#options)</sup> ] | bool     |

#### Instance
| Method               | Arguments       |  Returns |
|----------------------|:----------------|---------:|
| format               | bool longFormat |   string |
| getAge               | none            |      int |
| isMale               | none            |     bool |
| isFemale             | none            |     bool |
| isCoordinationNumber | none            |     bool |
| isInterimNumber      | none            |     bool |
| getDate              | none            | DateTime |

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
| Option                  | Type                     | Default                 | Description                       |
|-------------------------|:-------------------------|:------------------------|:----------------------------------|
| allowCoordinationNumber | bool                     | true                    | Accept coordination numbers       |
| allowInterimNumber      | bool                     | false                   | Accept interim/T numbers          |
| clock                   | Psr\Clock\ClockInterface | Personummer\SystemClock | Clock used for time calculations  |

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

(new Personnummer('1212121212'))->getAge();
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
