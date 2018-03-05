# personnummer [![Build Status](https://travis-ci.org/personnummer/php.svg?branch=master)](https://travis-ci.org/personnummer/php)

Validate Swedish social security numbers.

## Installation

```
composer require frozzare/personnummer
```

## Examples

### Validation

```php
use Frozzare\Personnummer\Personnummer;

Personnummer::valid(6403273813);
//=> true

Personnummer::valid('19130401+2931');
//=> true
```

### Format
```php
use Frozzare\Personnummer\Personnummer;

// Short format (YYMMDD-XXXX)
Personnummer::format(6403273813);
//=> 640327-3813

// Long format (YYYYMMDDXXXX)
Personnummer::valid('6403273813', true);
//=> 196403273813
```

See [PersonnummerTest.php](tests/PersonnummerTest.php) for more examples.

## License

MIT
